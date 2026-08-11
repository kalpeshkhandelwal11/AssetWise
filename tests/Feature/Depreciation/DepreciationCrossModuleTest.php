<?php

namespace Tests\Feature\Depreciation;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetDepreciationSetting;
use App\Models\DepreciationMethod;
use App\Models\DepreciationSettingRequest;
use App\Models\DisposalRequest;
use App\Models\DisposalType;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\User;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\DepreciationService;
use App\Services\DisposalService;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DepreciationCrossModuleTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function straightLine(): DepreciationMethod
    {
        return DepreciationMethod::firstOrCreate(
            ['code' => 'straight_line'],
            ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true],
        );
    }

    /** An active setting that is roughly half depreciated (start 6 months ago, 12-month life). */
    private function halfDepreciatedSetting(Asset $asset): AssetDepreciationSetting
    {
        $setting = AssetDepreciationSetting::create([
            'asset_id'                 => $asset->id,
            'depreciation_method_id'   => $this->straightLine()->id,
            'useful_life_months'       => 12,
            'salvage_value'            => 0,
            'start_date'               => now()->subMonths(6)->startOfMonth()->toDateString(),
            'cost_basis'               => 100000,
            'accumulated_depreciation' => 0,
            'current_book_value'       => 100000,
            'is_active'                => true,
        ]);

        app(DepreciationService::class)->generateSchedule($setting);
        app(DepreciationService::class)->postDuePeriods();

        return $setting->fresh();
    }

    public function test_disposal_write_off_stops_depreciation_and_records_gain_loss(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $asset = Asset::factory()->create();
        $setting = $this->halfDepreciatedSetting($asset);

        $disposal = DisposalRequest::create([
            'asset_id'         => $asset->id,
            'disposal_type_id' => DisposalType::firstOrCreate(['code' => 'SALE'], ['name' => 'Sale', 'is_active' => true])->id,
            'reason'           => 'Sold',
            'status'           => 'approved',
            'requested_by'     => $user->id,
        ]);

        app(DisposalService::class)->writeOff($disposal, ['disposal_value' => 70000], $user);

        $disposal->refresh();
        $this->assertNotNull($disposal->net_book_value_at_disposal);
        // gain/loss must equal proceeds - net book value, by construction.
        $this->assertEqualsWithDelta(
            70000 - (float) $disposal->net_book_value_at_disposal,
            (float) $disposal->gain_loss,
            0.01,
        );
        $this->assertFalse($setting->fresh()->is_active);
        $this->assertNotNull($setting->fresh()->stopped_at);
    }

    public function test_inter_company_transfer_resets_the_schedule_at_net_book_value(): void
    {
        $this->seedRolesAndPermissions();
        $asset = Asset::factory()->create();
        $old = $this->halfDepreciatedSetting($asset);

        $nbv = app(DepreciationService::class)->netBookValue($old, now());
        $new = app(DepreciationService::class)->resetForTransfer($asset->fresh(), now());

        $this->assertNotNull($new);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame($new->id, $old->fresh()->superseded_by);
        $this->assertTrue($new->is_active);
        $this->assertEqualsWithDelta($nbv, (float) $new->cost_basis, 0.01);
        $this->assertEqualsWithDelta(0, (float) $new->accumulated_depreciation, 0.01);
    }

    public function test_capitalizing_completed_maintenance_raises_a_depreciation_request(): void
    {
        // Capitalization routes through the depreciation approval workflow, so one must exist.
        $this->seedRolesAndPermissions();
        $workflow = ApprovalWorkflow::factory()->module('depreciation')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);

        $actor = User::factory()->create();
        $asset = Asset::factory()->create();
        $this->halfDepreciatedSetting($asset);

        $record = app(MaintenanceService::class)->log($asset->fresh(), [
            'maintenance_type_id'           => MaintenanceType::firstOrCreate(['code' => 'OVERHAUL'], ['name' => 'Overhaul', 'is_active' => true])->id,
            'status'                        => 'completed',
            'performed_date'                => now()->toDateString(),
            'cost'                          => 20000,
            'is_capitalized'                => true,
            'capitalized_amount'            => 20000,
            'additional_useful_life_months' => 12,
        ], $actor);

        $this->assertInstanceOf(MaintenanceRecord::class, $record);

        $request = DepreciationSettingRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame('capitalization', $request->change_type);
        $this->assertSame($record->id, $request->source_maintenance_id);
        $this->assertSame('pending_approval', $request->status);
        // New basis = net book value + capitalized amount.
        $this->assertGreaterThan(20000, (float) $request->cost_basis);
    }
}
