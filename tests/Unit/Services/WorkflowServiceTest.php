<?php

namespace Tests\Unit\Services;

use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestEscalated;
use App\Events\ApprovalRequestRejected;
use App\Events\ApprovalRequestSubmitted;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function service(): WorkflowService
    {
        return app(WorkflowService::class);
    }

    /** Two-level role workflow: Approver -> Asset Manager. */
    private function workflow(string $module = 'transfer', ?int $escalationHours = null): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::factory()->module($module)->create();

        ApprovalStep::factory()->forRole('Approver', 1, $escalationHours)->create(['workflow_id' => $workflow->id]);
        ApprovalStep::factory()->forRole('Asset Manager', 2, $escalationHours)->create(['workflow_id' => $workflow->id]);

        return $workflow->load('steps');
    }

    private function asset(): Asset
    {
        return Asset::factory()->create();
    }

    // ------------------------------------------------------------------ submit

    public function test_submit_throws_when_no_active_workflow_exists(): void
    {
        $this->seedRolesAndPermissions();

        $this->expectException(ValidationException::class);

        $this->service()->submit($this->asset(), 'transfer', User::factory()->create());
    }

    public function test_submit_throws_when_more_than_one_active_workflow_exists(): void
    {
        $this->seedRolesAndPermissions();
        $this->workflow();
        $this->workflow();

        $this->expectException(ValidationException::class);

        $this->service()->submit($this->asset(), 'transfer', User::factory()->create());
    }

    public function test_submit_ignores_inactive_and_other_module_workflows(): void
    {
        $this->seedRolesAndPermissions();
        $active = $this->workflow();
        $this->workflow()->update(['is_active' => false]);
        $this->workflow('disposal');

        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->assertSame($active->id, $request->workflow_id);
    }

    public function test_submit_creates_pending_request_at_the_lowest_step(): void
    {
        $this->seedRolesAndPermissions();
        $workflow = $this->workflow();
        $asset = $this->asset();
        $actor = User::factory()->create();

        Event::fake([ApprovalRequestSubmitted::class]);

        $request = $this->service()->submit($asset, 'transfer', $actor);

        $this->assertSame('pending', $request->status);
        $this->assertSame(1, $request->current_step);
        $this->assertSame($workflow->id, $request->workflow_id);
        $this->assertSame(Asset::class, $request->approvable_type);
        $this->assertSame($asset->id, $request->approvable_id);
        $this->assertSame($actor->id, $request->submitted_by);
        $this->assertNotNull($request->current_step_started_at);

        Event::assertDispatched(ApprovalRequestSubmitted::class);
    }

    public function test_submit_throws_when_the_workflow_has_no_steps(): void
    {
        $this->seedRolesAndPermissions();
        ApprovalWorkflow::factory()->create(['module' => 'transfer']);

        $this->expectException(ValidationException::class);

        $this->service()->submit($this->asset(), 'transfer', User::factory()->create());
    }

    public function test_submit_notifies_the_first_step_approvers_only(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $manager = $this->createUserWithRole('Asset Manager');
        $this->workflow();

        $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->assertCount(1, $approver->fresh()->notifications);
        $this->assertCount(0, $manager->fresh()->notifications);
    }

    // ----------------------------------------------------------------- approve

    public function test_approve_advances_to_the_next_step_and_resets_the_step_clock(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();

        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());
        $startedAt = $request->current_step_started_at;

        $this->travel(2)->hours();
        $request = $this->service()->approve($request, $approver, 'Looks fine');
        $this->travelBack();

        $this->assertSame('pending', $request->status);
        $this->assertSame(2, $request->current_step);
        $this->assertTrue($request->current_step_started_at->greaterThan($startedAt));

        $this->assertDatabaseHas('approval_actions', [
            'request_id' => $request->id,
            'step_level' => 1,
            'user_id'    => $approver->id,
            'action'     => 'approve',
            'comment'    => 'Looks fine',
        ]);
    }

    public function test_intermediate_approval_does_not_fire_the_approved_event(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        Event::fake([ApprovalRequestApproved::class]);

        $this->service()->approve($request, $approver);

        Event::assertNotDispatched(ApprovalRequestApproved::class);
    }

    public function test_final_approval_completes_the_request_and_fires_the_approved_event(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $manager = $this->createUserWithRole('Asset Manager');
        $this->workflow();

        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());
        $request = $this->service()->approve($request, $approver);

        Event::fake([ApprovalRequestApproved::class]);

        $request = $this->service()->approve($request, $manager);

        $this->assertSame('approved', $request->status);
        $this->assertDatabaseHas('approval_requests', ['id' => $request->id, 'status' => 'approved']);

        Event::assertDispatched(
            ApprovalRequestApproved::class,
            fn (ApprovalRequestApproved $e) => $e->request->is($request)
                && $e->finalApprover->is($manager)
                && $e->request->workflow->module === 'transfer'
                && $e->request->approvable instanceof Asset,
        );
    }

    public function test_approve_rejects_a_user_who_is_not_an_approver_for_the_current_step(): void
    {
        $manager = $this->createUserWithRole('Asset Manager'); // level 2, not level 1
        $this->workflow();
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->expectException(AuthorizationException::class);

        $this->service()->approve($request, $manager);
    }

    public function test_approve_throws_on_an_already_resolved_request(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());
        $this->service()->reject($request, $approver, 'No');

        $this->expectException(ValidationException::class);

        $this->service()->approve($request->fresh(), $approver);
    }

    // ------------------------------------------------------------------ reject

    public function test_reject_terminates_the_request_fires_the_event_and_leaves_the_subject_untouched(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $asset = $this->asset();
        $before = $asset->only(['name', 'status_id', 'company_id', 'updated_at']);

        $request = $this->service()->submit($asset, 'transfer', User::factory()->create());

        Event::fake([ApprovalRequestRejected::class]);

        $request = $this->service()->reject($request, $approver, 'Budget not approved');

        $this->assertSame('rejected', $request->status);
        $this->assertSame(1, $request->current_step, 'Rejection must not advance the step.');
        $this->assertDatabaseHas('approval_actions', [
            'request_id' => $request->id,
            'action'     => 'reject',
            'comment'    => 'Budget not approved',
        ]);

        $this->assertEquals($before, $asset->fresh()->only(['name', 'status_id', 'company_id', 'updated_at']));

        Event::assertDispatched(
            ApprovalRequestRejected::class,
            fn (ApprovalRequestRejected $e) => $e->reason === 'Budget not approved',
        );
    }

    public function test_reject_requires_a_comment(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->expectException(ValidationException::class);

        $this->service()->reject($request, $approver, '   ');
    }

    // ------------------------------------------------------------------ canAct

    public function test_can_act_matches_the_current_step_by_role(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->assertTrue($this->service()->canAct($approver, $request));
    }

    public function test_can_act_matches_the_current_step_by_user(): void
    {
        $this->seedRolesAndPermissions();
        $named = User::factory()->create();

        $workflow = ApprovalWorkflow::factory()->create(['module' => 'transfer']);
        ApprovalStep::factory()->forUser($named, 1)->create(['workflow_id' => $workflow->id]);

        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->assertTrue($this->service()->canAct($named, $request));
        $this->assertFalse($this->service()->canAct(User::factory()->create(), $request));
    }

    public function test_can_act_denies_the_next_level_approver_before_escalation(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $this->workflow('transfer', 48);
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->assertFalse($this->service()->canAct($manager, $request));
    }

    public function test_escalation_widens_eligibility_without_skipping_the_level(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $manager = $this->createUserWithRole('Asset Manager');
        $this->workflow('transfer', 48);
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->travel(49)->hours();
        $this->service()->escalate();
        $this->travelBack();

        $request->refresh();

        // Both may now act on the SAME step — the step itself did not advance.
        $this->assertSame(1, $request->current_step);
        $this->assertTrue($this->service()->canAct($approver, $request), 'Original approver must keep eligibility.');
        $this->assertTrue($this->service()->canAct($manager, $request), 'Escalation target gains eligibility.');
        $this->assertFalse($this->service()->canAct($this->createUserWithRole('Viewer'), $request));
    }

    public function test_escalating_the_last_step_falls_back_to_workflow_manage_holders(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $superAdmin = $this->createUserWithRole('Super Admin');
        $viewer = $this->createUserWithRole('Viewer');

        $workflow = ApprovalWorkflow::factory()->create(['module' => 'transfer']);
        ApprovalStep::factory()->forRole('Asset Manager', 1, 24)->create(['workflow_id' => $workflow->id]);

        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->travel(25)->hours();
        $this->service()->escalate();
        $this->travelBack();

        $request->refresh();

        $this->assertTrue($this->service()->canAct($manager, $request));
        $this->assertTrue($this->service()->canAct($superAdmin, $request), 'workflow.manage is the universal way out.');
        $this->assertFalse($this->service()->canAct($viewer, $request));
    }

    public function test_can_act_is_false_once_the_request_is_resolved(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());
        $request = $this->service()->reject($request, $approver, 'No');

        $this->assertFalse($this->service()->canAct($approver, $request));
    }

    // ---------------------------------------------------------------- escalate

    public function test_escalate_only_fires_after_the_configured_hours(): void
    {
        $this->createUserWithRole('Approver');
        $this->workflow('transfer', 48);
        $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->travel(47)->hours();
        $this->assertSame(0, $this->service()->escalate());

        $this->travel(2)->hours();
        $this->assertSame(1, $this->service()->escalate());
        $this->travelBack();
    }

    public function test_escalate_is_idempotent(): void
    {
        $this->createUserWithRole('Approver');
        $this->workflow('transfer', 48);
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->travel(49)->hours();
        $this->service()->escalate();
        $this->service()->escalate();
        $this->travelBack();

        $this->assertSame(1, ApprovalAction::where('request_id', $request->id)->where('action', 'escalate')->count());
    }

    public function test_escalate_skips_steps_with_no_escalation_hours(): void
    {
        $this->createUserWithRole('Approver');
        $this->workflow('transfer', null);
        $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        $this->travel(1000)->hours();
        $this->assertSame(0, $this->service()->escalate());
        $this->travelBack();
    }

    public function test_escalate_ignores_resolved_requests(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow('transfer', 48);
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());
        $this->service()->reject($request, $approver, 'No');

        $this->travel(49)->hours();
        $this->assertSame(0, $this->service()->escalate());
        $this->travelBack();
    }

    public function test_escalate_fires_the_escalated_event_and_records_a_system_action(): void
    {
        $this->createUserWithRole('Approver');
        $this->workflow('transfer', 48);
        $request = $this->service()->submit($this->asset(), 'transfer', User::factory()->create());

        Event::fake([ApprovalRequestEscalated::class]);

        $this->travel(49)->hours();
        $this->service()->escalate();
        $this->travelBack();

        $this->assertDatabaseHas('approval_actions', [
            'request_id' => $request->id,
            'step_level' => 1,
            'user_id'    => null,
            'action'     => 'escalate',
        ]);

        Event::assertDispatched(ApprovalRequestEscalated::class);
    }

    // ---------------------------------------------------------------- activate

    public function test_activate_deactivates_other_workflows_for_the_same_module(): void
    {
        $this->seedRolesAndPermissions();
        $first = $this->workflow();
        $other = ApprovalWorkflow::factory()->module('disposal')->create();
        $second = ApprovalWorkflow::factory()->module('transfer')->inactive()->create();

        $this->service()->activate($second);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertTrue($other->fresh()->is_active, 'Other modules are untouched.');
    }

    public function test_approvable_label_falls_back_to_class_and_id(): void
    {
        $this->seedRolesAndPermissions();
        $this->workflow();
        // A model with no getApprovalLabel() exercises the class-#id fallback. (Asset now
        // defines getApprovalLabel() for the creation-approval inbox, so it no longer does.)
        $company = \App\Models\Company::factory()->create();

        $request = $this->service()->submit($company, 'transfer', User::factory()->create());

        $this->assertSame('Company #' . $company->id, ApprovalRequest::find($request->id)->approvable_label);
    }
}
