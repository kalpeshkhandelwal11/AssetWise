<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetStatus;
use App\Models\Employee;
use App\Models\Location;
use App\Models\MaintenanceType;
use App\Models\MovementType;
use App\Services\MaintenanceService;
use App\Services\MovementService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Regression guards for the Nov 2026 logic audit: ways around an approval, and actions that
 * left records broken. Each test fails if its loophole reopens.
 */
class LoopholeRegressionTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function st(string $code): AssetStatus
    {
        return AssetStatus::firstOrCreate(['code' => $code], ['name' => ucfirst(strtolower($code)), 'color' => '#999999', 'is_system' => true, 'is_active' => true]);
    }

    private function editTo(Asset $asset, string $code)
    {
        return $this->actingAs($this->createUserWithRole('Asset Manager'))->put(route('assets.update', $asset), [
            'name' => $asset->name, 'category_id' => $asset->category_id,
            'asset_type_id' => $asset->asset_type_id, 'status_id' => $this->st($code)->id,
        ]);
    }

    private function workflow(string $module): void
    {
        $wf = ApprovalWorkflow::factory()->module($module)->create(['is_active' => true]);
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
    }

    // ------------------------------------------------------------------ status by hand

    #[DataProvider('workflowOwnedStatuses')]
    public function test_a_workflow_owned_status_cannot_be_left_by_editing(string $code): void
    {
        $asset = Asset::factory()->create(['status_id' => $this->st($code)->id]);

        $this->editTo($asset, 'AVAILABLE')->assertSessionHasErrors('status_id');

        $this->assertSame($code, $asset->fresh()->status->code);
    }

    public static function workflowOwnedStatuses(): array
    {
        return [['DRAFT'], ['IN_TRANSIT'], ['DISPOSED'], ['SOLD_EXT'], ['RETURNED']];
    }

    public function test_other_fields_still_save_while_the_status_is_workflow_owned(): void
    {
        $asset = Asset::factory()->create(['status_id' => $this->st('DRAFT')->id]);

        // The form shows the status read-only and posts no status_id at all.
        $this->actingAs($this->createUserWithRole('Asset Manager'))->put(route('assets.update', $asset), [
            'name' => 'Renamed draft', 'category_id' => $asset->category_id, 'asset_type_id' => $asset->asset_type_id,
        ])->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame('Renamed draft', $asset->name);
        $this->assertSame('DRAFT', $asset->status->code);
    }

    public function test_lost_cannot_be_picked_by_hand(): void
    {
        $asset = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id]);

        $this->editTo($asset, 'LOST')->assertSessionHasErrors('status_id');
        $this->assertSame('AVAILABLE', $asset->fresh()->status->code);
    }

    public function test_a_manual_status_change_is_written_to_the_status_ledger(): void
    {
        $asset = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id]);

        $this->editTo($asset, 'ASSIGNED')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $asset->id, 'to_status_id' => $this->st('ASSIGNED')->id,
        ]);
    }

    // ------------------------------------------------------------------ approvals

    public function test_submitter_cannot_approve_their_own_request_when_another_approver_exists(): void
    {
        $this->seedRolesAndPermissions();
        $this->workflow('asset_creation');
        $this->st('AVAILABLE');
        $submitter = $this->createUserWithRole('Approver');
        $other = $this->createUserWithRole('Approver');
        $asset = Asset::factory()->create(['status_id' => $this->st('DRAFT')->id]);
        $req = app(WorkflowService::class)->submit($asset, 'asset_creation', $submitter);

        $this->actingAs($submitter)->post(route('approvals.approve', $req))->assertForbidden();
        $this->assertSame('pending', $req->fresh()->status);

        $this->actingAs($other)->post(route('approvals.approve', $req))->assertSessionHasNoErrors();
        $this->assertSame('approved', $req->fresh()->status);
    }

    public function test_a_sole_approver_can_still_approve_their_own_request(): void
    {
        $this->seedRolesAndPermissions();
        $this->workflow('asset_creation');
        $this->st('AVAILABLE');
        $only = $this->createUserWithRole('Approver');
        $asset = Asset::factory()->create(['status_id' => $this->st('DRAFT')->id]);
        $req = app(WorkflowService::class)->submit($asset, 'asset_creation', $only);

        $this->actingAs($only)->post(route('approvals.approve', $req))->assertSessionHasNoErrors();
        $this->assertSame('approved', $req->fresh()->status);
    }

    // ------------------------------------------------------------------ movements

    public function test_a_movement_request_cannot_carry_a_target_status(): void
    {
        $this->seedRolesAndPermissions();
        $this->workflow('transfer');
        $this->st('ASSIGNED');
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id]);
        $type = MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true]);
        $data = ['movement_type_id' => $type->id, 'to_custodian_id' => Employee::factory()->create()->id, 'to_status_id' => $this->st('DISPOSED')->id];

        // HTTP: the field is dropped, so the approved assignment ends Assigned, not Scrapped.
        $this->actingAs($manager)->post(route('movements.store'), $data + ['asset_id' => $asset->id])->assertSessionHasNoErrors();
        $movement = AssetMovement::where('asset_id', $asset->id)->sole();
        $this->assertNull($movement->to_status_id);
        app(WorkflowService::class)->approve($movement->approvalRequest, $this->createUserWithRole('Approver'));
        $this->assertSame('ASSIGNED', $asset->fresh()->status->code);

        // Service: an internal caller passing one is refused outright.
        $second = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id]);
        $this->expectException(ValidationException::class);
        app(MovementService::class)->submit($second, $data, $manager);
    }

    // ------------------------------------------------------------------ delete

    public function test_an_asset_with_something_open_cannot_be_deleted(): void
    {
        $this->seedRolesAndPermissions();
        $this->workflow('asset_creation');
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create(['status_id' => $this->st('DRAFT')->id]);
        app(WorkflowService::class)->submit($asset, 'asset_creation', $admin);

        $this->actingAs($admin)->delete(route('assets.destroy', $asset))->assertSessionHas('error');
        $this->assertNotSoftDeleted('assets', ['id' => $asset->id]);

        // Bulk delete skips it and deletes the rest.
        $free = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id]);
        $this->actingAs($admin)->post(route('assets.bulk-delete'), ['asset_ids' => [$asset->id, $free->id]])
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted('assets', ['id' => $asset->id]);
        $this->assertSoftDeleted('assets', ['id' => $free->id]);
    }

    // ------------------------------------------------------------------ maintenance

    private function mtype(): MaintenanceType
    {
        return MaintenanceType::firstOrCreate(['code' => 'CORR'], ['name' => 'Corrective', 'is_active' => true]);
    }

    public function test_maintenance_cannot_start_on_a_scrapped_asset(): void
    {
        $actor = $this->createUserWithRole('Asset Manager');
        $this->st('MAINTENANCE');
        $asset = Asset::factory()->create(['status_id' => $this->st('DISPOSED')->id]);

        try {
            app(MaintenanceService::class)->log($asset, ['maintenance_type_id' => $this->mtype()->id, 'status' => 'in_progress'], $actor);
            $this->fail('maintenance started on a scrapped asset');
        } catch (ValidationException) {
        }

        $this->assertSame('DISPOSED', $asset->fresh()->status->code);
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function test_completing_maintenance_does_not_resurrect_an_asset_scrapped_meanwhile(): void
    {
        $actor = $this->createUserWithRole('Asset Manager');
        $this->st('MAINTENANCE');
        $asset = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id]);
        $svc = app(MaintenanceService::class);
        $record = $svc->log($asset, ['maintenance_type_id' => $this->mtype()->id, 'status' => 'in_progress'], $actor);
        $this->assertSame('MAINTENANCE', $asset->fresh()->status->code);

        $asset->update(['status_id' => $this->st('DISPOSED')->id]);
        $svc->update($record->fresh(), ['maintenance_type_id' => $this->mtype()->id, 'status' => 'completed'], $actor);

        $this->assertSame('DISPOSED', $asset->fresh()->status->code);
    }

    // ------------------------------------------------------------------ master data

    public function test_employee_edit_form_cannot_deactivate_someone_holding_assets(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $emp = Employee::factory()->create(['is_active' => true]);
        Asset::factory()->create(['custodian_id' => $emp->id]);

        $this->actingAs($admin)->put(route('admin.employees.update', $emp), [
            'name' => $emp->name, 'employee_code' => $emp->employee_code, 'email' => $emp->email,
            'company_id' => $emp->company_id, 'is_active' => 0,
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue((bool) $emp->fresh()->is_active);
    }

    public function test_a_location_in_use_cannot_be_deleted(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $used = Location::create(['name' => 'Plaza X', 'code' => 'PX', 'is_active' => true]);
        $empty = Location::create(['name' => 'Plaza Y', 'code' => 'PY', 'is_active' => true]);
        $asset = Asset::factory()->create(['location_id' => $used->id]);

        $this->actingAs($admin)->delete(route('admin.locations.destroy', $used))->assertSessionHas('error');
        $this->assertSame($used->id, $asset->fresh()->location_id);

        $this->actingAs($admin)->delete(route('admin.locations.destroy', $empty));
        $this->assertDatabaseMissing('locations', ['id' => $empty->id]);
    }

    public function test_a_category_with_assets_cannot_be_deleted(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create();

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $asset->category_id))->assertSessionHas('error');
        $this->assertNotSoftDeleted('asset_categories', ['id' => $asset->category_id]);
    }
}
