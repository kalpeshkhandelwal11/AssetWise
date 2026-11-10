<?php

namespace Tests\Feature\Approvals;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * A request pending 24h+ triggers ONE reminder (in-app + mail) to its current approvers and
 * its submitter. Not before 24h, never twice, never for resolved requests.
 */
class RemindApprovalsCommandTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private User $submitter;

    private User $approver;

    private ApprovalRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $workflow = ApprovalWorkflow::factory()->module('transfer')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);

        $this->submitter = User::factory()->create();
        $this->submitter->assignRole('Asset Manager');
        $this->approver = User::factory()->create();
        $this->approver->assignRole('Approver');

        $this->request = app(WorkflowService::class)->submit(Asset::factory()->create(), 'transfer', $this->submitter);
    }

    private function remindersFor(User $user): int
    {
        return $user->fresh()->notifications()->whereIn('type', ['approval.reminder', 'approval.reminder_submitter'])->count();
    }

    public function test_nothing_is_sent_before_24_hours(): void
    {
        $this->travel(23)->hours();
        $this->artisan('approvals:remind')->assertSuccessful();
        $this->travelBack();

        $this->assertSame(0, $this->remindersFor($this->approver));
        $this->assertNull($this->request->fresh()->reminder_sent_at);
    }

    public function test_after_24_hours_approver_and_submitter_are_reminded_once(): void
    {
        $this->travel(25)->hours();
        $this->artisan('approvals:remind')->expectsOutputToContain('1 approval request')->assertSuccessful();
        $this->artisan('approvals:remind')->expectsOutputToContain('No approval requests'); // second run: no repeat
        $this->travelBack();

        $this->assertSame(1, $this->remindersFor($this->approver));
        $this->assertSame(1, $this->remindersFor($this->submitter));
        $this->assertSame('approval.reminder_submitter', $this->submitter->fresh()->notifications->first()->type);
        $this->assertSame(1, $this->request->fresh()->reminder_sent_step);
    }

    public function test_resolved_requests_are_not_reminded(): void
    {
        app(WorkflowService::class)->approve($this->request, $this->approver);

        $this->travel(25)->hours();
        $this->artisan('approvals:remind');
        $this->travelBack();

        $this->assertSame(0, $this->remindersFor($this->submitter));
    }

    public function test_a_submitter_who_is_also_an_approver_gets_a_single_reminder(): void
    {
        $this->submitter->assignRole('Approver');

        $this->travel(25)->hours();
        $this->artisan('approvals:remind');
        $this->travelBack();

        $this->assertSame(1, $this->remindersFor($this->submitter));
        $this->assertSame('approval.reminder', $this->submitter->fresh()->notifications->first()->type);
    }

    public function test_reminder_is_scheduled_hourly(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('approvals:remind');
    }
}
