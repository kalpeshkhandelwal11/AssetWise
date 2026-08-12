<?php

namespace Tests\Feature\Kits;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetDepreciationSetting;
use App\Models\AssetMovement;
use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\DepreciationMethod;
use App\Models\Employee;
use App\Models\KitAssignment;
use App\Models\MovementType;
use App\Models\Setting;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\DepreciationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class KitAssignmentTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function kitWorkflow(): void
    {
        $this->seedRolesAndPermissions();
        $wf = ApprovalWorkflow::factory()->module('kit_assignment')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
    }

    private function transferWorkflow(): void
    {
        $wf = ApprovalWorkflow::factory()->module('transfer')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
    }

    private function movementType(string $code): MovementType
    {
        return MovementType::firstOrCreate(['code' => $code], ['name' => ucwords(strtolower(str_replace('_', ' ', $code))), 'is_active' => true]);
    }

    public function test_assigning_requires_kits_assign(): void
    {
        $this->kitWorkflow();
        $viewer = $this->createUserWithRole('Viewer');
        $asset = Asset::factory()->create();

        $this->actingAs($viewer)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$asset->id],
            'movement_type_id' => $this->movementType('ASSIGNMENT')->id,
            'to_custodian_id'  => $viewer->id,
        ])->assertForbidden();
    }

    public function test_single_mode_assign_then_approve_moves_all_assets(): void
    {
        $this->kitWorkflow();
        $manager = $this->createUserWithRole('Asset Manager');
        $approver = $this->createUserWithRole('Approver');
        $custodian = Employee::factory()->create();
        $a1 = Asset::factory()->create();
        $a2 = Asset::factory()->create();

        $this->actingAs($manager)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$a1->id, $a2->id],
            'movement_type_id' => $this->movementType('ASSIGNMENT')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertRedirect();

        $assignment = KitAssignment::firstOrFail();
        $this->assertSame('single', $assignment->approval_mode);
        $this->assertSame('pending_approval', $assignment->status);
        $this->assertNotNull($assignment->approval_request_id);

        $this->actingAs($approver)->post(route('approvals.approve', $assignment->approval_request_id))->assertRedirect();

        $this->assertSame('completed', $assignment->fresh()->status);
        $this->assertSame($custodian->id, $a1->fresh()->custodian_id);
        $this->assertSame($custodian->id, $a2->fresh()->custodian_id);
    }

    public function test_single_mode_rejection_frees_the_assets(): void
    {
        $this->kitWorkflow();
        $manager = $this->createUserWithRole('Asset Manager');
        $approver = $this->createUserWithRole('Approver');
        $custodian = Employee::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($manager)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$asset->id],
            'movement_type_id' => $this->movementType('ASSIGNMENT')->id,
            'to_custodian_id'  => $custodian->id,
        ]);

        $assignment = KitAssignment::firstOrFail();
        $this->actingAs($approver)->post(route('approvals.reject', $assignment->approval_request_id), ['comment' => 'No']);

        $this->assertSame('rejected', $assignment->fresh()->status);
        $this->assertNull($asset->fresh()->custodian_id);
        $this->assertFalse($asset->fresh()->hasPendingMovement());

        // Resubmission allowed.
        $this->actingAs($manager)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$asset->id],
            'movement_type_id' => $this->movementType('ASSIGNMENT')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('kit_assignments', 2);
    }

    public function test_per_asset_mode_creates_one_transfer_approval_per_asset(): void
    {
        $this->seedRolesAndPermissions();
        $this->transferWorkflow();
        Setting::set('kit_assignment_approval_mode', 'per_asset');

        $manager = $this->createUserWithRole('Asset Manager');
        $custodian = Employee::factory()->create();
        $a1 = Asset::factory()->create();
        $a2 = Asset::factory()->create();

        $this->actingAs($manager)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$a1->id, $a2->id],
            'movement_type_id' => $this->movementType('ASSIGNMENT')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertRedirect();

        $assignment = KitAssignment::firstOrFail();
        $this->assertSame('per_asset', $assignment->approval_mode);
        $this->assertNull($assignment->batch); // no batch in per_asset mode

        $movements = AssetMovement::where('kit_assignment_id', $assignment->id)->get();
        $this->assertCount(2, $movements);
        $this->assertTrue($movements->every(fn ($m) => $m->approval_request_id !== null));
    }

    public function test_return_kit_reverses_a_completed_assignment(): void
    {
        $this->kitWorkflow();
        $manager = $this->createUserWithRole('Asset Manager');
        $approver = $this->createUserWithRole('Approver');
        $custodian = Employee::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($manager)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$asset->id],
            'movement_type_id' => $this->movementType('ASSIGNMENT')->id,
            'to_custodian_id'  => $custodian->id,
        ]);
        $assignment = KitAssignment::firstOrFail();
        $this->actingAs($approver)->post(route('approvals.approve', $assignment->approval_request_id));
        $this->assertSame($custodian->id, $asset->fresh()->custodian_id);

        $this->movementType('RETURN');
        $this->actingAs($manager)->post(route('kit-assignments.return', $assignment))->assertRedirect();

        $return = KitAssignment::where('direction', 'return')->firstOrFail();
        $this->assertSame($assignment->id, $return->parent_assignment_id);

        $this->actingAs($approver)->post(route('approvals.approve', $return->approval_request_id));

        $this->assertNull($asset->fresh()->custodian_id); // RETURN clears custodian
        $this->assertSame('completed', $return->fresh()->status);
    }

    public function test_inter_company_kit_transfer_flips_company_and_resets_depreciation(): void
    {
        $this->kitWorkflow();
        $method = DepreciationMethod::firstOrCreate(
            ['code' => 'straight_line'],
            ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true],
        );
        $manager = $this->createUserWithRole('Asset Manager');
        $approver = $this->createUserWithRole('Approver');
        $companyB = Company::factory()->create();
        $asset = Asset::factory()->create();

        $setting = AssetDepreciationSetting::create([
            'asset_id'                 => $asset->id,
            'depreciation_method_id'   => $method->id,
            'useful_life_months'       => 60,
            'salvage_value'            => 0,
            'start_date'               => now()->subMonths(6)->startOfMonth()->toDateString(),
            'cost_basis'               => 100000,
            'accumulated_depreciation' => 0,
            'current_book_value'       => 100000,
            'is_active'                => true,
        ]);
        app(DepreciationService::class)->generateSchedule($setting);

        $this->actingAs($manager)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$asset->id],
            'movement_type_id' => $this->movementType('INTER_COMPANY_TRANSFER')->id,
            'to_company_id'    => $companyB->id,
        ])->assertRedirect();

        $assignment = KitAssignment::firstOrFail();
        $this->actingAs($approver)->post(route('approvals.approve', $assignment->approval_request_id));

        $this->assertSame($companyB->id, $asset->fresh()->company_id);
        $this->assertFalse($setting->fresh()->is_active); // superseded by the transfer reset
    }

    public function test_single_mode_without_a_configured_workflow_errors(): void
    {
        $this->seedRolesAndPermissions(); // no kit_assignment workflow
        $manager = $this->createUserWithRole('Asset Manager');
        $custodian = Employee::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($manager)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$asset->id],
            'movement_type_id' => $this->movementType('ASSIGNMENT')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertSessionHasErrors('workflow');

        $this->assertDatabaseCount('kit_assignments', 0);
    }

    public function test_disposed_asset_cannot_be_kitted(): void
    {
        $this->kitWorkflow();
        $disposed = AssetStatus::firstOrCreate(['code' => 'DISPOSED'], ['name' => 'Disposed', 'color' => '#ef4444', 'is_system' => true, 'is_active' => true]);
        $manager = $this->createUserWithRole('Asset Manager');
        $custodian = Employee::factory()->create();
        $asset = Asset::factory()->create(['status_id' => $disposed->id]);

        $this->actingAs($manager)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$asset->id],
            'movement_type_id' => $this->movementType('ASSIGNMENT')->id,
            'to_custodian_id'  => $custodian->id,
        ])->assertSessionHasErrors('asset');

        $this->assertDatabaseCount('kit_assignments', 0);
    }
}
