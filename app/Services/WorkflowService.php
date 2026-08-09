<?php

namespace App\Services;

use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestEscalated;
use App\Events\ApprovalRequestRejected;
use App\Events\ApprovalRequestSubmitted;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Polymorphic multi-level approval engine (M08).
 *
 * Deliberately knows nothing about the modules it approves for: consumers submit any
 * Eloquent model and listen for ApprovalRequestApproved to run their domain logic.
 */
class WorkflowService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    /**
     * Open a new approval request for $approvable against the active workflow for $module.
     *
     * Requires exactly one active workflow — 0 or >1 is a configuration error the admin
     * UI must prevent (see activate()), not something to paper over by picking one.
     */
    public function submit(Model $approvable, string $module, User $actor): ApprovalRequest
    {
        $workflows = ApprovalWorkflow::query()
            ->where('module', $module)
            ->where('is_active', true)
            ->with('steps')
            ->get();

        if ($workflows->isEmpty()) {
            throw ValidationException::withMessages([
                'workflow' => "No active approval workflow is configured for '{$module}'.",
            ]);
        }

        if ($workflows->count() > 1) {
            throw ValidationException::withMessages([
                'workflow' => "More than one active approval workflow is configured for '{$module}'. Exactly one must be active.",
            ]);
        }

        $workflow = $workflows->first();
        $firstStep = $workflow->steps->sortBy('level')->first();

        if (! $firstStep) {
            throw ValidationException::withMessages([
                'workflow' => "Workflow '{$workflow->name}' has no approval steps configured.",
            ]);
        }

        $request = ApprovalRequest::create([
            'workflow_id'             => $workflow->id,
            'approvable_type'         => $approvable->getMorphClass(),
            'approvable_id'           => $approvable->getKey(),
            'status'                  => 'pending',
            'current_step'            => $firstStep->level,
            'current_step_started_at' => now(),
            'submitted_by'            => $actor->id,
        ]);

        $request->setRelation('workflow', $workflow);
        $request->setRelation('approvable', $approvable);

        event(new ApprovalRequestSubmitted($request, $actor));
        $this->notifyStepApprovers($request, $firstStep, 'approval.pending');

        return $request;
    }

    /**
     * Approve the current step. Advances to the next step, or completes the request and
     * fires ApprovalRequestApproved when this was the final step.
     *
     * Returns the refreshed request (the plan's small deviation from a void signature —
     * the controller needs the post-action state for its redirect message).
     */
    public function approve(ApprovalRequest $request, User $actor, ?string $comment = null): ApprovalRequest
    {
        $this->assertActionable($request, $actor);

        $request->loadMissing('workflow.steps');
        $level = $request->current_step;
        $nextStep = $request->nextStepAfter($level);

        DB::transaction(function () use ($request, $actor, $comment, $level, $nextStep) {
            ApprovalAction::create([
                'request_id' => $request->id,
                'step_level' => $level,
                'user_id'    => $actor->id,
                'action'     => 'approve',
                'comment'    => $comment,
                'created_at' => now(),
            ]);

            $request->update($nextStep
                ? ['current_step' => $nextStep->level, 'current_step_started_at' => now()]
                : ['status' => 'approved']);
        });

        $request->refresh()->load(['workflow.steps', 'approvable', 'submittedBy']);

        if ($nextStep) {
            $this->notifyStepApprovers($request, $nextStep, 'approval.pending');

            return $request;
        }

        if ($request->submittedBy) {
            $this->notifications->send($request->submittedBy, 'approval.approved', $this->payload($request));
        }

        event(new ApprovalRequestApproved($request, $actor));

        return $request;
    }

    /**
     * Reject terminates the request (decision P8.2 — no edit-in-place resubmit; the
     * consumer calls submit() again to open a fresh request).
     */
    public function reject(ApprovalRequest $request, User $actor, string $comment): ApprovalRequest
    {
        $this->assertActionable($request, $actor);

        if (trim($comment) === '') {
            throw ValidationException::withMessages([
                'comment' => 'A reason is required when rejecting a request.',
            ]);
        }

        $level = $request->current_step;

        DB::transaction(function () use ($request, $actor, $comment, $level) {
            ApprovalAction::create([
                'request_id' => $request->id,
                'step_level' => $level,
                'user_id'    => $actor->id,
                'action'     => 'reject',
                'comment'    => $comment,
                'created_at' => now(),
            ]);

            $request->update(['status' => 'rejected']);
        });

        $request->refresh()->load(['workflow.steps', 'approvable', 'submittedBy']);

        if ($request->submittedBy) {
            $this->notifications->send($request->submittedBy, 'approval.rejected', $this->payload($request) + [
                'reason' => $comment,
            ]);
        }

        event(new ApprovalRequestRejected($request, $actor, $comment));

        return $request;
    }

    /**
     * Escalate every pending request whose current step has been open longer than its
     * configured escalation_hours. Idempotent: a step that already has an `escalate`
     * action row is skipped, so re-running the daily command changes nothing.
     *
     * Returns the number of requests escalated (handy for the command's output).
     */
    public function escalate(): int
    {
        $candidates = ApprovalRequest::query()
            ->select('approval_requests.*', 'approval_steps.escalation_hours as step_escalation_hours')
            ->join('approval_steps', function ($join) {
                $join->on('approval_steps.workflow_id', '=', 'approval_requests.workflow_id')
                     ->on('approval_steps.level', '=', 'approval_requests.current_step');
            })
            ->where('approval_requests.status', 'pending')
            ->whereNotNull('approval_steps.escalation_hours')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('approval_actions')
                  ->whereColumn('approval_actions.request_id', 'approval_requests.id')
                  ->whereColumn('approval_actions.step_level', 'approval_requests.current_step')
                  ->where('approval_actions.action', 'escalate');
            })
            ->with('workflow.steps')
            ->get()
            // The "is it due yet" arithmetic stays in PHP: adding a *column* number of
            // hours to a timestamp has no portable SQL form across MySQL and the SQLite
            // used by the test suite, and the row count here is tiny.
            ->filter(fn (ApprovalRequest $r) => $r->current_step_started_at
                ->copy()
                ->addHours((int) $r->step_escalation_hours)
                ->isPast());

        foreach ($candidates as $request) {
            $step = $request->currentStepDefinition();

            if (! $step) {
                continue;
            }

            ApprovalAction::create([
                'request_id' => $request->id,
                'step_level' => $request->current_step,
                'user_id'    => null, // system-generated
                'action'     => 'escalate',
                'comment'    => "Escalated automatically after {$step->escalation_hours} hours.",
                'created_at' => now(),
            ]);

            $this->notifyEscalationTargets($request);

            event(new ApprovalRequestEscalated($request, $step));
        }

        return $candidates->count();
    }

    /**
     * Single source of truth for "may this user act on this request right now".
     * ApprovalRequestPolicy delegates here; the inbox filters with it too.
     *
     * Escalation *widens* eligibility for the same step rather than skipping a level
     * (decision P8.1): once escalated, the level+1 approver may also act, and whoever
     * acts first resolves the step.
     */
    public function canAct(User $user, ApprovalRequest $request): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        $request->loadMissing('workflow.steps');
        $step = $request->currentStepDefinition();

        if (! $step) {
            return false;
        }

        if ($this->matchesStep($user, $step)) {
            return true;
        }

        if (! $this->hasEscalated($request)) {
            return false;
        }

        $escalationStep = $request->nextStepAfter($request->current_step);

        // Escalating on the last step has nowhere higher to go — fall back to anyone who
        // can manage workflows (Super Admin always qualifies), so there is always a way out.
        return $escalationStep
            ? $this->matchesStep($user, $escalationStep)
            : $user->can('workflow.manage');
    }

    /**
     * Enforce the "exactly one active workflow per module" invariant from the admin side:
     * activating one deactivates its siblings instead of erroring at submit() time.
     */
    public function activate(ApprovalWorkflow $workflow): void
    {
        DB::transaction(function () use ($workflow) {
            ApprovalWorkflow::where('module', $workflow->module)
                ->where('id', '!=', $workflow->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $workflow->update(['is_active' => true]);
        });
    }

    /** Users currently eligible to act, used for notifications and the inbox. */
    public function eligibleApprovers(ApprovalRequest $request): Collection
    {
        $request->loadMissing('workflow.steps');
        $step = $request->currentStepDefinition();

        if (! $step) {
            return new Collection();
        }

        $users = $this->approversForStep($step);

        if ($this->hasEscalated($request)) {
            $users = $users->merge($this->escalationTargets($request));
        }

        return $users->unique('id')->values();
    }

    /**
     * The approver inbox: every pending request $user may act on right now.
     *
     * Eligibility depends on per-row escalation state, so the filter runs in PHP rather
     * than SQL. A pure-SQL version would roughly double the complexity of escalate()'s
     * query for a feature with no consumer modules live yet — a deliberate, flagged
     * scaling deferral, revisit when M09/M13 start producing real volume.
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function pendingFor(User $user): Collection
    {
        return ApprovalRequest::query()
            ->where('status', 'pending')
            ->with(['workflow.steps', 'approvable', 'submittedBy'])
            ->latest('id')
            ->get()
            ->filter(fn (ApprovalRequest $request) => $this->canAct($user, $request))
            ->values();
    }

    public function hasEscalated(ApprovalRequest $request): bool
    {
        return ApprovalAction::where('request_id', $request->id)
            ->where('step_level', $request->current_step)
            ->where('action', 'escalate')
            ->exists();
    }

    // ---------------------------------------------------------------- internals

    private function assertActionable(ApprovalRequest $request, User $actor): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'This request has already been ' . $request->status . '.',
            ]);
        }

        if (! $this->canAct($actor, $request)) {
            throw new AuthorizationException('You are not an approver for the current step of this request.');
        }
    }

    private function matchesStep(User $user, ApprovalStep $step): bool
    {
        return $step->approver_type === 'role'
            ? $step->approver_role !== null && $user->hasRole($step->approver_role)
            : (int) $step->approver_user_id === (int) $user->id;
    }

    /** @return Collection<int, User> */
    private function approversForStep(ApprovalStep $step): Collection
    {
        if ($step->approver_type === 'user') {
            $user = $step->approverUser()->where('is_active', true)->first();

            return new Collection($user ? [$user] : []);
        }

        if (! $step->approver_role || ! Role::where('name', $step->approver_role)->where('guard_name', 'web')->exists()) {
            return new Collection();
        }

        return User::role($step->approver_role)->where('is_active', true)->get();
    }

    /** @return Collection<int, User> */
    private function escalationTargets(ApprovalRequest $request): Collection
    {
        $escalationStep = $request->nextStepAfter($request->current_step);

        return $escalationStep
            ? $this->approversForStep($escalationStep)
            : User::permission('workflow.manage')->where('is_active', true)->get();
    }

    private function notifyStepApprovers(ApprovalRequest $request, ApprovalStep $step, string $type): void
    {
        $this->notifications->sendMany(
            $this->approversForStep($step),
            $type,
            $this->payload($request) + ['step_level' => $step->level],
        );
    }

    private function notifyEscalationTargets(ApprovalRequest $request): void
    {
        $this->notifications->sendMany(
            $this->escalationTargets($request),
            'approval.escalated',
            $this->payload($request) + ['step_level' => $request->current_step],
        );
    }

    private function payload(ApprovalRequest $request): array
    {
        return [
            'request_id' => $request->id,
            'module'     => $request->workflow?->module,
            'workflow'   => $request->workflow?->name,
            'subject'    => $request->approvable_label,
            'url'        => route('approvals.show', $request),
        ];
    }
}
