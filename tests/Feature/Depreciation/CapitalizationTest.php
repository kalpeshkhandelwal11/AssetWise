<?php

namespace Tests\Feature\Depreciation;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetDepreciationSetting;
use App\Models\DepreciationMethod;
use App\Models\DepreciationScheduleLine;
use App\Models\DepreciationSettingRequest;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\DepreciationService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Repair / maintenance cost capitalisation from the maintenance form, and the schedule rule it
 * relies on: when a schedule is replaced or stopped, no day is ever depreciated twice.
 */
class CapitalizationTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $wf = ApprovalWorkflow::factory()->module('depreciation')->create(['is_active' => true]);
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
    }

    private function depreciating(Asset $asset, int $months = 12, int $startMonthsAgo = 6): AssetDepreciationSetting
    {
        $method = DepreciationMethod::firstOrCreate(['code' => 'straight_line'], ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true]);
        $setting = AssetDepreciationSetting::create([
            'asset_id' => $asset->id, 'depreciation_method_id' => $method->id, 'useful_life_months' => $months,
            'salvage_value' => 0, 'start_date' => now()->subMonths($startMonthsAgo)->startOfMonth()->toDateString(),
            'cost_basis' => 100000, 'accumulated_depreciation' => 0, 'current_book_value' => 100000, 'is_active' => true,
        ]);
        app(DepreciationService::class)->generateSchedule($setting);
        app(DepreciationService::class)->postDuePeriods();

        return $setting->fresh();
    }

    private function type(): MaintenanceType
    {
        return MaintenanceType::firstOrCreate(['code' => 'OVERHAUL'], ['name' => 'Overhaul', 'is_active' => true]);
    }

    private function form(array $extra = []): array
    {
        return $extra + [
            'maintenance_type_id' => $this->type()->id,
            'status'              => 'completed',
            'performed_date'      => now()->toDateString(),
            'cost'                => 30000,
        ];
    }

    public function test_capitalising_a_repair_from_the_form_goes_for_approval_and_then_into_the_schedule(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $old = $this->depreciating($asset);
        $performed = now()->subMonths(2)->startOfMonth()->addDays(15);   // the 16th, two months ago

        $this->actingAs($manager)->post(route('assets.maintenance.store', $asset), $this->form([
            'performed_date' => $performed->toDateString(),
            'is_capitalized' => 1, 'capitalized_amount' => 20000, 'additional_useful_life_months' => 6,
        ]))->assertSessionHasNoErrors();

        $record = MaintenanceRecord::sole();
        $request = DepreciationSettingRequest::where('source_maintenance_id', $record->id)->sole();
        $this->assertSame('capitalization', $request->change_type);
        $this->assertStringContainsString('Capitalisation', $request->getApprovalLabel());
        $this->assertStringContainsString('20,000.00 added', $request->getApprovalLabel());
        $this->assertSame('pending', $record->fresh()->capitalizationStatus());

        app(WorkflowService::class)->approve($request->approvalRequest, $this->createUserWithRole('Approver'));

        $old->refresh();
        $new = $asset->fresh()->activeDepreciationSetting();
        $this->assertFalse($old->is_active);
        $this->assertSame($performed->toDateString(), $new->start_date->toDateString());

        // The old schedule ends the day before the work: nothing after that month, and that
        // month prorated to the 1st–15th.
        $this->assertSame(0, DepreciationScheduleLine::where('asset_depreciation_setting_id', $old->id)
            ->where(fn ($q) => $q->where('period_year', '>', $performed->year)
                ->orWhere(fn ($q2) => $q2->where('period_year', $performed->year)->where('period_month', '>', $performed->month)))
            ->count());
        $oldEdge = DepreciationScheduleLine::where('asset_depreciation_setting_id', $old->id)
            ->where('period_year', $performed->year)->where('period_month', $performed->month)->sole();
        $this->assertSame(15, (int) $oldEdge->days_in_period);

        // New basis = book value at the end of the 15th + the capitalised amount.
        $oldDepreciated = (float) DepreciationScheduleLine::where('asset_depreciation_setting_id', $old->id)->sum('depreciation_amount');
        $this->assertEqualsWithDelta(100000 - $oldDepreciated + 20000, (float) $new->cost_basis, 0.05);

        // Life: what was left on the 16th, plus 6.
        $this->assertSame(12 - 4 + 6, (int) $new->useful_life_months);

        // No day is depreciated twice: in the change month, old + new days = days in month.
        $newEdge = DepreciationScheduleLine::where('asset_depreciation_setting_id', $new->id)
            ->where('period_year', $performed->year)->where('period_month', $performed->month)->sole();
        $this->assertSame($performed->daysInMonth, (int) $oldEdge->days_in_period + (int) $newEdge->days_in_period);

        $this->assertSame('applied', $record->fresh()->capitalizationStatus());
    }

    public function test_capitalisation_cannot_be_changed_once_submitted_and_is_not_submitted_twice(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $this->depreciating($asset);

        $this->actingAs($manager)->post(route('assets.maintenance.store', $asset), $this->form([
            'is_capitalized' => 1, 'capitalized_amount' => 10000,
        ]));
        $record = MaintenanceRecord::sole();

        // Saving again (even with a different amount) changes nothing while it is pending.
        $this->actingAs($manager)->put(route('assets.maintenance.update', [$asset, $record]), $this->form([
            'is_capitalized' => 1, 'capitalized_amount' => 25000, 'vendor' => 'ACME',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, DepreciationSettingRequest::where('source_maintenance_id', $record->id)->count());
        $this->assertEqualsWithDelta(10000, (float) $record->fresh()->capitalized_amount, 0.01);
        $this->assertSame('ACME', $record->fresh()->vendor);

        $this->actingAs($manager)->get(route('assets.maintenance.edit', [$asset, $record]))
            ->assertOk()->assertSee('waiting for depreciation approval');
    }

    public function test_capitalisation_can_be_ticked_later_on_a_completed_record(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $this->depreciating($asset);

        $this->actingAs($manager)->post(route('assets.maintenance.store', $asset), $this->form());
        $record = MaintenanceRecord::sole();
        $this->assertSame(0, DepreciationSettingRequest::count());

        $this->actingAs($manager)->put(route('assets.maintenance.update', [$asset, $record]), $this->form([
            'is_capitalized' => 1, 'capitalized_amount' => 5000,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, DepreciationSettingRequest::where('source_maintenance_id', $record->id)->count());
    }

    public function test_capitalisation_is_refused_when_the_asset_is_not_depreciating_or_exceeds_the_cost(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $plain = Asset::factory()->create();

        $this->actingAs($manager)->post(route('assets.maintenance.store', $plain), $this->form([
            'is_capitalized' => 1, 'capitalized_amount' => 1000,
        ]))->assertSessionHasErrors('is_capitalized');
        $this->assertSame(0, MaintenanceRecord::count()); // the whole save rolled back

        $this->actingAs($manager)->get(route('assets.maintenance.create', $plain))
            ->assertOk()->assertSee('this asset is not being depreciated');

        $asset = Asset::factory()->create();
        $this->depreciating($asset);
        $this->actingAs($manager)->post(route('assets.maintenance.store', $asset), $this->form([
            'cost' => 1000, 'is_capitalized' => 1, 'capitalized_amount' => 5000,
        ]))->assertSessionHasErrors('capitalized_amount');
    }

    public function test_stopping_mid_month_prorates_that_month_instead_of_keeping_it_whole(): void
    {
        $asset = Asset::factory()->create();
        $setting = $this->depreciating($asset, 12, 1);   // started on the 1st of last month
        $stopOn = Carbon::parse($setting->start_date)->addMonth()->addDays(9);   // the 10th of this month

        app(DepreciationService::class)->stop($setting, $stopOn);

        $last = DepreciationScheduleLine::where('asset_depreciation_setting_id', $setting->id)
            ->orderByDesc('period_year')->orderByDesc('period_month')->first();
        $this->assertSame($stopOn->month, (int) $last->period_month);
        $this->assertSame(10, (int) $last->days_in_period);
        $this->assertFalse($setting->fresh()->is_active);
    }
}
