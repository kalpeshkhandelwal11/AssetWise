<?php

namespace Tests\Feature\Console;

use App\Models\ApprovalAction;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class EscalateApprovalsCommandTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function pendingRequest(int $escalationHours = 48)
    {
        $this->createUserWithRole('Approver');

        $workflow = ApprovalWorkflow::factory()->module('transfer')->create();
        ApprovalStep::factory()->forRole('Approver', 1, $escalationHours)->create(['workflow_id' => $workflow->id]);
        ApprovalStep::factory()->forRole('Asset Manager', 2, $escalationHours)->create(['workflow_id' => $workflow->id]);

        return app(WorkflowService::class)->submit(
            Asset::factory()->create(),
            'transfer',
            User::factory()->create(),
        );
    }

    public function test_command_escalates_a_request_past_its_escalation_window(): void
    {
        $request = $this->pendingRequest();

        $this->travel(49)->hours();
        $this->artisan('approvals:escalate')
             ->expectsOutputToContain('Escalated 1 approval request(s).')
             ->assertSuccessful();
        $this->travelBack();

        $this->assertDatabaseHas('approval_actions', [
            'request_id' => $request->id,
            'step_level' => 1,
            'action'     => 'escalate',
            'user_id'    => null,
        ]);
    }

    public function test_a_second_immediate_run_does_not_duplicate_the_escalation(): void
    {
        $request = $this->pendingRequest();

        $this->travel(49)->hours();
        $this->artisan('approvals:escalate')->assertSuccessful();
        $this->artisan('approvals:escalate')
             ->expectsOutputToContain('No approval requests were due for escalation.')
             ->assertSuccessful();
        $this->travelBack();

        $this->assertSame(1, ApprovalAction::where('request_id', $request->id)->where('action', 'escalate')->count());
    }

    public function test_command_does_nothing_before_the_window_elapses(): void
    {
        $this->pendingRequest();

        $this->travel(47)->hours();
        $this->artisan('approvals:escalate')
             ->expectsOutputToContain('No approval requests were due for escalation.')
             ->assertSuccessful();
        $this->travelBack();

        $this->assertDatabaseCount('approval_actions', 0);
    }
}
