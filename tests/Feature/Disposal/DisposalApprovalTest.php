<?php

namespace Tests\Feature\Disposal;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\DisposalRequest;
use App\Models\DisposalType;
use App\Models\Employee;
use App\Models\MovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * 'disposal' deliberately has no seeded default workflow (M08 decision — proves workflows
 * are configurable without code changes), so every test here builds its own single-step
 * workflow inline instead of using WorkflowSeeder, mirroring how an admin would configure
 * one via /admin/workflows.
 */
class DisposalApprovalTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function disposalWorkflow(string $approverRole = 'Approver'): ApprovalWorkflow
    {
        $this->seedRolesAndPermissions();
        $workflow = ApprovalWorkflow::factory()->module('disposal')->create();
        ApprovalStep::factory()->forRole($approverRole, 1)->create(['workflow_id' => $workflow->id]);

        return $workflow;
    }

    private function disposalType(): DisposalType
    {
        return DisposalType::firstOrCreate(['code' => 'SCRAP'], ['name' => 'Scrap', 'is_active' => true]);
    }

    public function test_submit_requires_disposal_request_permission(): void
    {
        $this->disposalWorkflow();
        $asset = Asset::factory()->create();
        $user = $this->createUserWithRole('Viewer'); // no disposal.request

        $this->actingAs($user)->post(route('disposals.store'), [
            'asset_id'         => $asset->id,
            'disposal_type_id' => $this->disposalType()->id,
            'reason'           => 'End of life',
        ])->assertForbidden();
    }

    public function test_submit_fails_without_a_configured_workflow(): void
    {
        $this->seedRolesAndPermissions(); // no workflow created
        $asset = Asset::factory()->create();
        $requester = $this->createUserWithRole('Asset Manager');

        $this->actingAs($requester)->post(route('disposals.store'), [
            'asset_id'         => $asset->id,
            'disposal_type_id' => $this->disposalType()->id,
            'reason'           => 'End of life',
        ])->assertSessionHasErrors('workflow');

        $this->assertDatabaseCount('disposal_requests', 0);
    }

    public function test_already_disposed_asset_cannot_be_disposed_again(): void
    {
        $this->disposalWorkflow();
        $disposed = AssetStatus::create(['name' => 'Disposed', 'code' => 'DISPOSED', 'color' => '#ef4444', 'is_system' => true, 'is_active' => true]);
        $asset = Asset::factory()->create(['status_id' => $disposed->id]);
        $requester = $this->createUserWithRole('Asset Manager');

        $this->actingAs($requester)->post(route('disposals.store'), [
            'asset_id'         => $asset->id,
            'disposal_type_id' => $this->disposalType()->id,
            'reason'           => 'End of life',
        ])->assertSessionHasErrors('asset');
    }

    public function test_asset_with_a_pending_movement_cannot_be_disposed(): void
    {
        $this->disposalWorkflow();
        $transferWorkflow = ApprovalWorkflow::factory()->module('transfer')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $transferWorkflow->id]);

        $asset = Asset::factory()->create();
        $requester = $this->createUserWithRole('Asset Manager');
        $custodian = Employee::factory()->create();

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true])->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertRedirect();

        $this->actingAs($requester)->post(route('disposals.store'), [
            'asset_id'         => $asset->id,
            'disposal_type_id' => $this->disposalType()->id,
            'reason'           => 'End of life',
        ])->assertSessionHasErrors('asset');
    }

    public function test_full_lifecycle_submit_approve_write_off_scrap(): void
    {
        $this->disposalWorkflow('Approver');
        AssetStatus::firstOrCreate(['code' => 'DISPOSED'], ['name' => 'Disposed', 'color' => '#ef4444', 'is_system' => true, 'is_active' => true]);
        $requester = $this->createUserWithRole('Asset Manager');
        $approver = $this->createUserWithRole('Approver');
        $completer = $this->createUserWithRole('Asset Manager');

        $asset = Asset::factory()->create();

        $this->actingAs($requester)->post(route('disposals.store'), [
            'asset_id'         => $asset->id,
            'disposal_type_id' => $this->disposalType()->id,
            'reason'           => 'End of life',
        ])->assertRedirect();

        $disposal = DisposalRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame('pending_approval', $disposal->status);
        $this->assertNotNull($disposal->approval_request_id);

        // Terminal (only) step approves -> MarkDisposalApproved -> status 'approved', asset untouched.
        $this->actingAs($approver)
             ->post(route('approvals.approve', $disposal->approval_request_id))
             ->assertRedirect(route('approvals.index'));

        $this->assertSame('approved', $disposal->fresh()->status);
        $this->assertNotSame('DISPOSED', $asset->fresh()->status?->code);

        // Cannot scrap before write-off.
        $this->actingAs($completer)
             ->post(route('disposals.scrap', $disposal))
             ->assertSessionHasErrors('status');

        $this->actingAs($completer)
             ->post(route('disposals.write-off', $disposal), ['disposal_value' => 150.50])
             ->assertRedirect(route('disposals.show', $disposal));

        $disposal->refresh();
        $this->assertSame('written_off', $disposal->status);
        $this->assertSame('150.50', $disposal->disposal_value);
        $this->assertSame($completer->id, $disposal->written_off_by);

        $this->actingAs($completer)
             ->post(route('disposals.scrap', $disposal))
             ->assertRedirect(route('disposals.show', $disposal));

        $disposal->refresh();
        $this->assertSame('scrapped', $disposal->status);
        $this->assertSame($completer->id, $disposal->scrapped_by);
        $this->assertSame('DISPOSED', $asset->fresh()->status?->code);

        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id'     => $asset->id,
            'to_status_id' => $asset->fresh()->status_id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $requester->id,
            'notifiable_type' => \App\Models\User::class,
            'type'            => 'disposal.completed',
        ]);
    }

    public function test_rejection_leaves_the_asset_untouched_and_unblocks_resubmission(): void
    {
        $this->disposalWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $approver = $this->createUserWithRole('Approver');
        $asset = Asset::factory()->create();

        $this->actingAs($requester)->post(route('disposals.store'), [
            'asset_id'         => $asset->id,
            'disposal_type_id' => $this->disposalType()->id,
            'reason'           => 'End of life',
        ]);

        $disposal = DisposalRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($approver)
             ->post(route('approvals.reject', $disposal->approval_request_id), ['comment' => 'Still in use']);

        $this->assertSame('rejected', $disposal->fresh()->status);
        $this->assertFalse($asset->fresh()->isDisposed());

        $this->actingAs($requester)->post(route('disposals.store'), [
            'asset_id'         => $asset->id,
            'disposal_type_id' => $this->disposalType()->id,
            'reason'           => 'Second attempt',
        ])->assertRedirect();

        $this->assertDatabaseCount('disposal_requests', 2);
    }

    public function test_movement_is_blocked_while_a_disposal_is_in_progress(): void
    {
        $this->disposalWorkflow();
        $transferWorkflow = ApprovalWorkflow::factory()->module('transfer')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $transferWorkflow->id]);

        $requester = $this->createUserWithRole('Asset Manager');
        $custodian = Employee::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($requester)->post(route('disposals.store'), [
            'asset_id'         => $asset->id,
            'disposal_type_id' => $this->disposalType()->id,
            'reason'           => 'End of life',
        ])->assertRedirect();

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true])->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertSessionHasErrors('asset');
    }

    public function test_disposal_show_is_restricted_to_participants(): void
    {
        $this->disposalWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $stranger = $this->createUserWithRole('Viewer');
        $asset = Asset::factory()->create();

        $this->actingAs($requester)->post(route('disposals.store'), [
            'asset_id'         => $asset->id,
            'disposal_type_id' => $this->disposalType()->id,
            'reason'           => 'End of life',
        ]);
        $disposal = DisposalRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($stranger)->get(route('disposals.show', $disposal))->assertForbidden();
        $this->actingAs($requester)->get(route('disposals.show', $disposal))->assertOk();
    }
}
