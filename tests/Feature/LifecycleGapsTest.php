<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLossRequest;
use App\Models\AssetMovement;
use App\Models\AssetMovementBatch;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\AuditItem;
use App\Models\CategoryDepreciationDefault;
use App\Models\Company;
use App\Models\DepreciationMethod;
use App\Models\DepreciationSettingRequest;
use App\Models\DisposalRequest;
use App\Models\Employee;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\MovementType;
use App\Models\User;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\DepreciationService;
use App\Services\DisposalService;
use App\Services\MaintenanceService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The lifecycle stages added in the Nov 2026 completeness pass: restore, end-of-life cleanup,
 * opening assignment, audit follow-up, maintenance reminders / recurrence, acknowledgement,
 * exit clearance and revaluation.
 */
class LifecycleGapsTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function st(string $code): AssetStatus
    {
        return AssetStatus::firstOrCreate(['code' => $code], ['name' => ucfirst(strtolower($code)), 'color' => '#999999', 'is_system' => true, 'is_active' => true]);
    }

    private function mvType(string $code): MovementType
    {
        return MovementType::firstOrCreate(['code' => $code], ['name' => ucwords(strtolower(str_replace('_', ' ', $code))), 'is_active' => true]);
    }

    private function workflow(string $module): void
    {
        $wf = ApprovalWorkflow::factory()->module($module)->create(['is_active' => true]);
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
    }

    // ------------------------------------------------------------------ restore

    public function test_deleted_assets_can_be_restored(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create(['serial_number' => 'SN-1']);
        $asset->delete();

        $this->actingAs($admin)->post(route('assets.bulk-restore'), ['asset_ids' => [$asset->id]])
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_restore_is_refused_when_the_serial_was_reused_meanwhile(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create(['serial_number' => 'SN-1']);
        $asset->delete();
        Asset::factory()->create(['serial_number' => 'sn-1']);

        $this->actingAs($admin)->post(route('assets.bulk-restore'), ['asset_ids' => [$asset->id]])
            ->assertSessionHas('error');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    // ------------------------------------------------------------------ end of life

    public function test_scrap_clears_the_custodian_and_frees_kit_slots(): void
    {
        $actor = $this->createUserWithRole('Super Admin');
        $this->st('DISPOSED');
        $asset = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id, 'custodian_id' => Employee::factory()->create()->id]);
        $kitId = DB::table('kits')->insertGetId(['name' => 'Kit', 'code' => 'K1', 'is_active' => true, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $itemId = DB::table('kit_items')->insertGetId(['kit_id' => $kitId, 'label' => 'Slot', 'category_id' => $asset->category_id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('kit_assets')->insert(['kit_item_id' => $itemId, 'asset_id' => $asset->id, 'created_at' => now(), 'updated_at' => now()]);
        $disposal = DisposalRequest::factory()->create(['asset_id' => $asset->id, 'status' => 'written_off']);

        app(DisposalService::class)->finalize($disposal, $actor);

        $asset->refresh();
        $this->assertSame('DISPOSED', $asset->status->code);
        $this->assertNull($asset->custodian_id);
        $this->assertDatabaseCount('kit_assets', 0);
    }

    // ------------------------------------------------------------------ opening assignment

    public function test_a_custodian_set_at_creation_gets_an_opening_movement_and_assigned_status(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $this->st('ASSIGNED');
        $this->mvType('ASSIGNMENT');
        $employee = Employee::factory()->create();

        $this->actingAs($manager)->post(route('assets.store'), [
            'name'          => 'Opening Laptop',
            'company_id'    => Company::factory()->create()->id,
            'category_id'   => AssetCategory::factory()->create()->id,
            'asset_type_id' => AssetType::firstOrCreate(['code' => 'HW'], ['name' => 'HW', 'is_active' => true])->id,
            'status_id'     => $this->st('AVAILABLE')->id,
            'custodian_id'  => $employee->id,
        ])->assertSessionHasNoErrors();

        $asset = Asset::where('name', 'Opening Laptop')->firstOrFail();
        $this->assertSame('ASSIGNED', $asset->status->code);

        $movement = AssetMovement::where('asset_id', $asset->id)->sole();
        $this->assertSame($employee->id, $movement->to_custodian_id);
        $this->assertSame('completed', $movement->status);
    }

    // ------------------------------------------------------------------ audit follow-up

    public function test_a_missing_audit_finding_can_be_sent_for_a_loss_write_off(): void
    {
        $this->seedRolesAndPermissions();
        $this->workflow('asset_loss');
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id]);
        $item = AuditItem::factory()->create(['asset_id' => $asset->id, 'status' => 'missing', 'notes' => 'Not at desk']);

        $this->actingAs($admin)->post(route('audits.items.report-loss', $item))->assertSessionHas('success');

        $loss = AssetLossRequest::where('asset_id', $asset->id)->sole();
        $this->assertSame('pending_approval', $loss->status);
        // The finding alone still changes nothing on the asset.
        $this->assertSame('AVAILABLE', $asset->fresh()->status->code);

        // A second click is refused, not duplicated.
        $this->actingAs($admin)->post(route('audits.items.report-loss', $item))->assertSessionHas('error');
        $this->assertSame(1, AssetLossRequest::count());
    }

    public function test_only_a_missing_finding_can_be_reported_as_lost(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $item = AuditItem::factory()->create(['status' => 'verified']);

        $this->actingAs($admin)->post(route('audits.items.report-loss', $item))->assertStatus(422);
    }

    // ------------------------------------------------------------------ maintenance

    private function mtype(): MaintenanceType
    {
        return MaintenanceType::firstOrCreate(['code' => 'PREV'], ['name' => 'Preventive', 'is_active' => true]);
    }

    public function test_maintenance_reminders_fire_on_the_threshold_days_only(): void
    {
        $manager = $this->createUserWithRole('Asset Manager'); // holds maintenance.manage
        $asset = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id]);
        $make = fn ($date, $status = 'scheduled') => MaintenanceRecord::factory()->create([
            'asset_id' => $asset->id, 'maintenance_type_id' => $this->mtype()->id, 'status' => $status, 'scheduled_date' => $date,
        ]);
        $make(now()->addDays(7));
        $make(now()->addDays(1));
        $make(now()->subDay());                    // overdue since yesterday
        $make(now()->addDays(3));                  // not a threshold day
        $make(now()->addDays(7), 'completed');     // already done

        $this->artisan('maintenance:remind')->expectsOutputToContain('Sent 3')->assertSuccessful();

        $this->assertSame(3, $manager->notifications()->where('data->type', 'maintenance_due')->count()
            ?: $manager->notifications()->count());
    }

    public function test_completing_recurring_maintenance_schedules_the_next_one(): void
    {
        $actor = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['status_id' => $this->st('AVAILABLE')->id]);
        $svc = app(MaintenanceService::class);
        $record = $svc->log($asset, [
            'maintenance_type_id' => $this->mtype()->id, 'status' => 'scheduled',
            'scheduled_date' => '2026-11-01', 'repeat_every_months' => 6,
        ], $actor);

        $svc->update($record, ['status' => 'completed', 'performed_date' => '2026-11-10'], $actor);

        $next = MaintenanceRecord::where('asset_id', $asset->id)->where('status', 'scheduled')->sole();
        $this->assertSame('2027-05-10', $next->scheduled_date->toDateString());
        $this->assertSame(6, (int) $next->repeat_every_months);

        // Saving the completed record again must not schedule another.
        $svc->update($record->fresh(), ['status' => 'completed', 'vendor' => 'ACME'], $actor);
        $this->assertSame(1, MaintenanceRecord::where('asset_id', $asset->id)->where('status', 'scheduled')->count());
    }

    // ------------------------------------------------------------------ acknowledgement

    private function assignedMovement(Employee $employee): AssetMovement
    {
        $asset = Asset::factory()->create(['status_id' => $this->st('ASSIGNED')->id, 'custodian_id' => $employee->id]);

        return AssetMovement::create([
            'asset_id' => $asset->id, 'movement_type_id' => $this->mvType('ASSIGNMENT')->id,
            'to_custodian_id' => $employee->id, 'status' => 'completed',
            'requested_by' => User::factory()->create()->id,
        ]);
    }

    public function test_a_custodian_with_a_login_acknowledges_their_assignment(): void
    {
        $this->seedRolesAndPermissions();
        $user = $this->createUserWithRole('Department User');
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $movement = $this->assignedMovement($employee);

        $this->assertTrue($movement->awaitsAcknowledgement());
        $this->assertNotNull($movement->asset->pendingAcknowledgement());

        // Someone else without assign rights cannot acknowledge for them.
        $this->actingAs($this->createUserWithRole('Viewer'))->post(route('movements.acknowledge', $movement))->assertForbidden();

        $this->actingAs($user)->post(route('movements.acknowledge', $movement))->assertSessionHas('success');

        $movement->refresh();
        $this->assertNotNull($movement->acknowledged_at);
        $this->assertSame($user->id, $movement->acknowledged_by);
        $this->assertNull($movement->asset->pendingAcknowledgement());
    }

    public function test_no_acknowledgement_is_awaited_from_an_employee_without_a_login(): void
    {
        $movement = $this->assignedMovement(Employee::factory()->create(['user_id' => null]));

        $this->assertFalse($movement->awaitsAcknowledgement());
        $this->assertNull($movement->asset->pendingAcknowledgement());
    }

    // ------------------------------------------------------------------ exit clearance

    public function test_exit_clearance_lists_custody_and_raises_one_return_for_all(): void
    {
        $this->seedRolesAndPermissions();
        $this->workflow('transfer');
        $this->mvType('RETURN');
        $admin = $this->createUserWithRole('Super Admin');
        $employee = Employee::factory()->create();
        $a = Asset::factory()->create(['status_id' => $this->st('ASSIGNED')->id, 'custodian_id' => $employee->id, 'name' => 'Leaver Laptop']);
        $b = Asset::factory()->create(['status_id' => $this->st('ASSIGNED')->id, 'custodian_id' => $employee->id, 'name' => 'Leaver Phone']);

        $this->actingAs($admin)->get(route('admin.employees.clearance', $employee))
            ->assertOk()->assertSee('Leaver Laptop')->assertSee('Leaver Phone')->assertSee('Raise return for all');

        // Deactivating is blocked and points here.
        $this->actingAs($admin)->patch(route('admin.employees.toggle', $employee))
            ->assertRedirect(route('admin.employees.clearance', $employee));
        $this->assertTrue((bool) $employee->fresh()->is_active);

        $this->actingAs($admin)->post(route('admin.employees.clearance.return', $employee), ['notes' => 'Last day'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $batch = AssetMovementBatch::sole();
        $this->assertSame(2, AssetMovement::where('batch_id', $batch->id)->count());
        $this->assertNotNull($batch->approval_request_id);
    }

    public function test_exit_clearance_shows_cleared_when_nothing_is_held(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $employee = Employee::factory()->create();

        $this->actingAs($admin)->get(route('admin.employees.clearance', $employee))
            ->assertOk()->assertSee('Cleared.')->assertSee('Deactivate employee');
    }

    // ------------------------------------------------------------------ revaluation

    public function test_revaluation_goes_for_approval_and_restates_the_book_value(): void
    {
        $this->seedRolesAndPermissions();
        $this->workflow('depreciation');
        $manager = $this->createUserWithRole('Asset Manager');
        $method = DepreciationMethod::firstOrCreate(['code' => 'straight_line'], ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true]);
        $asset = Asset::factory()->create(['purchase_cost' => 100000, 'purchase_date' => now()->subYear()->toDateString()]);
        CategoryDepreciationDefault::create(['category_id' => $asset->category_id, 'depreciation_method_id' => $method->id, 'useful_life_months' => 60, 'salvage_percent' => 5, 'start_basis' => 'purchase_date']);
        app(DepreciationService::class)->applyCategoryDefault($asset->fresh());

        $this->actingAs($manager)->post(route('assets.depreciation.revalue', $asset), [
            'new_value' => 60000, 'effective_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $request = DepreciationSettingRequest::where('asset_id', $asset->id)->sole();
        $this->assertSame('revaluation', $request->change_type);
        $this->assertStringContainsString('Impairment', $request->getApprovalLabel());

        app(WorkflowService::class)->approve($request->approvalRequest, $this->createUserWithRole('Approver'));

        $asset->refresh();
        $setting = $asset->activeDepreciationSetting();
        $this->assertEqualsWithDelta(60000, (float) $setting->cost_basis, 0.01);
        $this->assertSame(48, (int) $setting->useful_life_months); // 60 months less the year already used
        // Historical cost is untouched.
        $this->assertEqualsWithDelta(100000, (float) $asset->purchase_cost, 0.01);
    }

    public function test_revaluation_is_refused_when_the_asset_is_not_depreciated(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();

        $this->actingAs($manager)->post(route('assets.depreciation.revalue', $asset), [
            'new_value' => 5000, 'effective_date' => now()->toDateString(),
        ])->assertSessionHasErrors('new_value');
    }
}
