<?php

namespace Tests\Feature\Movement;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\MovementType;
use App\Services\MovementService;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * End-to-end: submit a movement, approve through both levels of the seeded 'transfer'
 * workflow (Approver -> Asset Manager), and confirm ApplyAssetMovement (the
 * ApprovalRequestApproved listener) actually applied the change. Deliberately does NOT
 * fake ApprovalRequestApproved/Rejected — proving the listeners fire for real is the point.
 */
class MovementApprovalTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function withWorkflow(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(WorkflowSeeder::class);
    }

    private function movementType(string $code, string $name): MovementType
    {
        return MovementType::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
    }

    public function test_submit_requires_a_movement_permission(): void
    {
        $this->withWorkflow();
        $asset = Asset::factory()->create();
        $custodian = Employee::factory()->create();
        $user = $this->createUserWithRole('Viewer'); // no movement.* permission

        $this->actingAs($user)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertForbidden();
    }

    public function test_disposed_asset_cannot_be_moved(): void
    {
        $this->withWorkflow();
        $disposed = AssetStatus::create(['name' => 'Disposed', 'code' => 'DISPOSED', 'color' => '#ef4444', 'is_system' => true, 'is_active' => true]);
        $asset = Asset::factory()->create(['status_id' => $disposed->id]);
        $custodian = Employee::factory()->create();
        $requester = $this->createUserWithRole('Asset Manager');

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertSessionHasErrors('asset');

        $this->assertDatabaseCount('asset_movements', 0);
    }

    public function test_asset_with_a_pending_movement_cannot_submit_another(): void
    {
        $this->withWorkflow();
        $asset = Asset::factory()->create();
        $custodianA = Employee::factory()->create();
        $custodianB = Employee::factory()->create();
        $requester = $this->createUserWithRole('Asset Manager');

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodianA->id,
        ])->assertRedirect();

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodianB->id,
        ])->assertSessionHasErrors('asset');

        $this->assertDatabaseCount('asset_movements', 1);
    }

    public function test_full_approval_chain_assigns_custodian_and_notifies_them(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Approver');
        $levelTwoApprover = $this->createUserWithRole('Asset Manager');
        // Custodian linked to a login account so the movement.completed notification lands.
        $custodianUser = $this->createUserWithRole('Viewer');
        $custodian = Employee::factory()->create(['user_id' => $custodianUser->id]);

        $asset = Asset::factory()->create();

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertRedirect(route('assets.show', $asset));

        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame('pending_approval', $movement->status);
        $this->assertNotNull($movement->approval_request_id);

        // Level 1 (Approver) approves — advances, nothing applied yet.
        $this->actingAs($levelOneApprover)
             ->post(route('approvals.approve', $movement->approval_request_id))
             ->assertRedirect(route('approvals.index'));

        $this->assertNull($asset->fresh()->custodian_id);
        $this->assertSame('pending_approval', $movement->fresh()->status);

        // Level 2 (final, Asset Manager) approves — terminal step fires
        // ApprovalRequestApproved -> ApplyAssetMovement -> MovementService::apply().
        $this->actingAs($levelTwoApprover)
             ->post(route('approvals.approve', $movement->approval_request_id))
             ->assertRedirect(route('approvals.index'));

        $this->assertSame($custodian->id, $asset->fresh()->custodian_id);
        $this->assertSame('completed', $movement->fresh()->status);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $custodianUser->id,
            'notifiable_type' => \App\Models\User::class,
            'type'            => 'movement.completed',
        ]);
    }

    public function test_assignment_approval_flips_status_to_assigned(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Approver');
        $levelTwoApprover = $this->createUserWithRole('Asset Manager');
        $custodian = Employee::factory()->create();
        $assigned = AssetStatus::firstOrCreate(['code' => 'ASSIGNED'], ['name' => 'Assigned', 'color' => '#3b82f6', 'is_system' => true, 'is_active' => true]);
        $asset = Asset::factory()->create(); // factory gives some non-ASSIGNED status

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodian->id,
        ]);

        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->firstOrFail();
        $this->actingAs($levelOneApprover)->post(route('approvals.approve', $movement->approval_request_id));
        $this->actingAs($levelTwoApprover)->post(route('approvals.approve', $movement->approval_request_id));

        $this->assertSame('ASSIGNED', $asset->fresh()->status->code);
        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id'     => $asset->id,
            'to_status_id' => $assigned->id,
        ]);
    }

    public function test_return_approval_flips_status_to_available(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Approver');
        $levelTwoApprover = $this->createUserWithRole('Asset Manager');
        AssetStatus::firstOrCreate(['code' => 'AVAILABLE'], ['name' => 'Available', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
        $custodian = Employee::factory()->create();
        $asset = Asset::factory()->create(['custodian_id' => $custodian->id]);

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('RETURN', 'Return')->id,
        ]);

        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->firstOrFail();
        $this->actingAs($levelOneApprover)->post(route('approvals.approve', $movement->approval_request_id));
        $this->actingAs($levelTwoApprover)->post(route('approvals.approve', $movement->approval_request_id));

        $this->assertNull($asset->fresh()->custodian_id);
        $this->assertSame('AVAILABLE', $asset->fresh()->status->code);
    }

    public function test_assigning_to_a_login_less_employee_completes_without_a_notification(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Approver');
        $levelTwoApprover = $this->createUserWithRole('Asset Manager');
        // No linked user — the custodian cannot be notified, but the movement must still apply.
        $custodian = Employee::factory()->create(['user_id' => null]);
        $asset = Asset::factory()->create();

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertRedirect(route('assets.show', $asset));

        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->firstOrFail();
        $this->actingAs($levelOneApprover)->post(route('approvals.approve', $movement->approval_request_id));
        $this->actingAs($levelTwoApprover)->post(route('approvals.approve', $movement->approval_request_id));

        $this->assertSame($custodian->id, $asset->fresh()->custodian_id);
        $this->assertSame('completed', $movement->fresh()->status);
        $this->assertDatabaseMissing('notifications', ['type' => 'movement.completed']);
    }

    public function test_rejection_leaves_the_asset_untouched_and_unblocks_resubmission(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Approver');
        $custodian = Employee::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodian->id,
        ]);

        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($levelOneApprover)
             ->post(route('approvals.reject', $movement->approval_request_id), ['comment' => 'Not needed']);

        $this->assertNull($asset->fresh()->custodian_id);
        $this->assertSame('rejected', $movement->fresh()->status);
        $this->assertDatabaseHas('approval_requests', ['id' => $movement->approval_request_id, 'status' => 'rejected']);

        // The pending-movement guard is now clear, so a fresh submission succeeds.
        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseCount('asset_movements', 2);
    }

    public function test_inter_company_transfer_updates_company_id_on_completion(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Approver');
        $levelTwoApprover = $this->createUserWithRole('Asset Manager');

        $originCompany = Company::factory()->create();
        $destinationCompany = Company::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $originCompany->id]);

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('INTER_COMPANY_TRANSFER', 'Inter-Company Transfer')->id,
            'to_company_id'    => $destinationCompany->id,
        ])->assertRedirect(route('assets.show', $asset));

        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($levelOneApprover)->post(route('approvals.approve', $movement->approval_request_id));
        $this->actingAs($levelTwoApprover)->post(route('approvals.approve', $movement->approval_request_id));

        $this->assertSame($destinationCompany->id, $asset->fresh()->company_id);
    }

    public function test_inter_company_transfer_rejects_the_same_company_as_destination(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $company = Company::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $company->id]);

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('INTER_COMPANY_TRANSFER', 'Inter-Company Transfer')->id,
            'to_company_id'    => $company->id,
        ])->assertSessionHasErrors('to_company_id');

        $this->assertDatabaseCount('asset_movements', 0);
    }

    public function test_return_clears_the_custodian_on_completion(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Approver');
        $levelTwoApprover = $this->createUserWithRole('Asset Manager');
        $custodian = Employee::factory()->create();
        $asset = Asset::factory()->create(['custodian_id' => $custodian->id]);

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('RETURN', 'Return')->id,
        ])->assertRedirect(route('assets.show', $asset));

        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->firstOrFail();
        $this->actingAs($levelOneApprover)->post(route('approvals.approve', $movement->approval_request_id));
        $this->actingAs($levelTwoApprover)->post(route('approvals.approve', $movement->approval_request_id));

        $this->assertNull($asset->fresh()->custodian_id);
    }

    public function test_bulk_batch_moves_every_selected_asset_under_one_approval(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Approver');
        $levelTwoApprover = $this->createUserWithRole('Asset Manager');

        $department = Department::create(['name' => 'IT', 'code' => 'IT', 'is_active' => true]);
        $assets = Asset::factory()->count(3)->create();

        $this->actingAs($requester)->post(route('movements.bulk.store'), [
            'asset_ids'        => $assets->pluck('id')->all(),
            'movement_type_id' => $this->movementType('TRANSFER', 'Transfer')->id,
            'to_department_id' => $department->id,
        ])->assertRedirect(route('movements.index'));

        $this->assertDatabaseCount('asset_movement_batches', 1);
        $this->assertDatabaseCount('asset_movements', 3);

        $batch = \App\Models\AssetMovementBatch::firstOrFail();
        $this->assertNotNull($batch->approval_request_id);

        $this->actingAs($levelOneApprover)->post(route('approvals.approve', $batch->approval_request_id));
        $this->actingAs($levelTwoApprover)->post(route('approvals.approve', $batch->approval_request_id));

        $this->assertSame('completed', $batch->fresh()->status);
        foreach ($assets as $asset) {
            $this->assertSame($department->id, $asset->fresh()->department_id);
        }
        $this->assertSame(3, \App\Models\AssetMovement::where('status', 'completed')->count());
    }

    public function test_bulk_assignment_assigns_the_employee_custodian_to_every_asset(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Approver');
        $levelTwoApprover = $this->createUserWithRole('Asset Manager');
        $custodian = Employee::factory()->create();
        $assets = Asset::factory()->count(2)->create();

        // A non-employee id must be rejected — the bulk custodian validates against employees.
        $this->actingAs($requester)->post(route('movements.bulk.store'), [
            'asset_ids'        => $assets->pluck('id')->all(),
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => 999999,
        ])->assertSessionHasErrors('to_custodian_id');

        $this->actingAs($requester)->post(route('movements.bulk.store'), [
            'asset_ids'        => $assets->pluck('id')->all(),
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertRedirect(route('movements.index'));

        $batch = \App\Models\AssetMovementBatch::firstOrFail();
        $this->actingAs($levelOneApprover)->post(route('approvals.approve', $batch->approval_request_id));
        $this->actingAs($levelTwoApprover)->post(route('approvals.approve', $batch->approval_request_id));

        foreach ($assets as $asset) {
            $this->assertSame($custodian->id, $asset->fresh()->custodian_id);
        }
    }

    public function test_verify_requires_completed_status_and_permission(): void
    {
        $this->withWorkflow();
        $requester = $this->createUserWithRole('Asset Manager');
        $verifier = $this->createUserWithRole('Approver'); // has movement.verify
        $custodian = Employee::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($requester)->post(route('movements.store'), [
            'asset_id'         => $asset->id,
            'movement_type_id' => $this->movementType('ASSIGNMENT', 'Assignment')->id,
            'to_custodian_id'  => $custodian->id,
        ]);
        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->firstOrFail();

        // Still pending_approval — verify is rejected before completion.
        $this->actingAs($verifier)
             ->post(route('movements.verify', $movement))
             ->assertSessionHasErrors('movement');

        $levelOneApprover = $this->createUserWithRole('Approver');
        $levelTwoApprover = $this->createUserWithRole('Asset Manager');
        $this->actingAs($levelOneApprover)->post(route('approvals.approve', $movement->approval_request_id));
        $this->actingAs($levelTwoApprover)->post(route('approvals.approve', $movement->approval_request_id));

        $this->actingAs($verifier)
             ->post(route('movements.verify', $movement->fresh()))
             ->assertRedirect();

        $this->assertNotNull($movement->fresh()->verified_at);
        $this->assertSame($verifier->id, $movement->fresh()->verified_by);
    }
}
