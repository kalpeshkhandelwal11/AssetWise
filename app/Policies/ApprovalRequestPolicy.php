<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\WorkflowService;

class ApprovalRequestPolicy
{
    public function __construct(private WorkflowService $workflow)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('workflow.approve') || $user->can('workflow.manage');
    }

    /**
     * Who may read the request and its history: the submitter, anyone currently eligible
     * to act, anyone who already acted on it (canAct goes false the moment a request is
     * resolved — participants must not lose the audit trail they contributed to), and
     * workflow admins.
     */
    public function view(User $user, ApprovalRequest $request): bool
    {
        return (int) $request->submitted_by === (int) $user->id
            || $this->workflow->canAct($user, $request)
            || $request->actions()->where('user_id', $user->id)->exists()
            || $user->can('workflow.manage');
    }

    /** Eligibility is instance-scoped — delegate entirely to the one source of truth. */
    public function act(User $user, ApprovalRequest $request): bool
    {
        return $this->workflow->canAct($user, $request);
    }
}
