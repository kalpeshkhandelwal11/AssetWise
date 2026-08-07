<?php

namespace Tests\Feature\Approvals;

use App\Events\ApprovalRequestApproved;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ApprovalControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function service(): WorkflowService
    {
        return app(WorkflowService::class);
    }

    /** Two-level role workflow: Approver -> Asset Manager. */
    private function workflow(?int $escalationHours = null): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::factory()->module('transfer')->create();

        ApprovalStep::factory()->forRole('Approver', 1, $escalationHours)->create(['workflow_id' => $workflow->id]);
        ApprovalStep::factory()->forRole('Asset Manager', 2, $escalationHours)->create(['workflow_id' => $workflow->id]);

        return $workflow;
    }

    private function submit(): ApprovalRequest
    {
        return $this->service()->submit(Asset::factory()->create(), 'transfer', User::factory()->create());
    }

    public function test_inbox_requires_auth(): void
    {
        $this->get(route('approvals.index'))->assertRedirect('/login');
    }

    public function test_inbox_is_forbidden_without_approval_permissions(): void
    {
        $this->actingAs($this->createUserWithRole('Viewer'))
             ->get(route('approvals.index'))
             ->assertForbidden();
    }

    public function test_inbox_shows_only_requests_the_user_may_act_on(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $manager = $this->createUserWithRole('Asset Manager');
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($approver)
             ->get(route('approvals.index'))
             ->assertOk()
             ->assertSee($request->approvable_label);

        $this->actingAs($manager)
             ->get(route('approvals.index'))
             ->assertOk()
             ->assertSee('Nothing awaiting your approval');
    }

    public function test_inbox_includes_escalation_widened_requests(): void
    {
        $this->createUserWithRole('Approver');
        $manager = $this->createUserWithRole('Asset Manager');
        $this->workflow(48);
        $request = $this->submit();

        $this->travel(49)->hours();
        $this->service()->escalate();
        $this->travelBack();

        $this->actingAs($manager)
             ->get(route('approvals.index'))
             ->assertOk()
             ->assertSee($request->approvable_label);
    }

    public function test_show_is_visible_to_the_submitter(): void
    {
        $this->createUserWithRole('Approver');
        $this->workflow();

        $submitter = User::factory()->create();
        $request = $this->service()->submit(Asset::factory()->create(), 'transfer', $submitter);

        $this->actingAs($submitter)
             ->get(route('approvals.show', $request))
             ->assertOk()
             ->assertSee('Approval Chain');
    }

    public function test_show_stays_visible_to_a_past_approver_after_the_request_resolves(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($approver)->post(route('approvals.reject', $request), ['comment' => 'No']);

        // canAct() is false now — the audit trail must still be readable by whoever acted.
        $this->actingAs($approver)
             ->get(route('approvals.show', $request))
             ->assertOk()
             ->assertSee('History');
    }

    public function test_show_is_forbidden_to_an_unrelated_user(): void
    {
        $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($this->createUserWithRole('Viewer'))
             ->get(route('approvals.show', $request))
             ->assertForbidden();
    }

    public function test_approve_advances_the_step(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($approver)
             ->post(route('approvals.approve', $request), ['comment' => 'Fine by me'])
             ->assertRedirect(route('approvals.index'))
             ->assertSessionHas('success');

        $this->assertSame(2, $request->fresh()->current_step);
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_final_approval_completes_the_request(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $manager = $this->createUserWithRole('Asset Manager');
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($approver)->post(route('approvals.approve', $request));

        Event::fake([ApprovalRequestApproved::class]);

        $this->actingAs($manager)
             ->post(route('approvals.approve', $request->fresh()))
             ->assertRedirect(route('approvals.index'));

        $this->assertSame('approved', $request->fresh()->status);
        Event::assertDispatched(ApprovalRequestApproved::class);
    }

    public function test_approve_is_forbidden_for_a_user_outside_the_current_step(): void
    {
        $this->createUserWithRole('Approver');
        $manager = $this->createUserWithRole('Asset Manager'); // level 2 while the request sits at level 1
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($manager)
             ->post(route('approvals.approve', $request))
             ->assertForbidden();

        $this->assertSame(1, $request->fresh()->current_step);
    }

    public function test_approve_is_forbidden_without_approval_permissions(): void
    {
        $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($this->createUserWithRole('Viewer'))
             ->post(route('approvals.approve', $request))
             ->assertForbidden();
    }

    public function test_reject_requires_a_comment(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($approver)
             ->post(route('approvals.reject', $request), ['comment' => ''])
             ->assertSessionHasErrors('comment');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_reject_terminates_the_request(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($approver)
             ->post(route('approvals.reject', $request), ['comment' => 'Not this quarter'])
             ->assertRedirect(route('approvals.index'));

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertDatabaseHas('approval_actions', [
            'request_id' => $request->id,
            'action'     => 'reject',
            'comment'    => 'Not this quarter',
        ]);
    }

    public function test_a_resolved_request_cannot_be_acted_on_again(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $request = $this->submit();

        $this->actingAs($approver)->post(route('approvals.reject', $request), ['comment' => 'No']);

        $this->actingAs($approver)
             ->post(route('approvals.approve', $request->fresh()))
             ->assertForbidden();
    }

    public function test_sidebar_links_to_the_inbox_for_approvers(): void
    {
        $approver = $this->createUserWithRole('Approver');
        $this->workflow();
        $this->submit();

        $this->actingAs($approver)
             ->get(route('dashboard'))
             ->assertOk()
             ->assertSee(route('approvals.index'));
    }
}
