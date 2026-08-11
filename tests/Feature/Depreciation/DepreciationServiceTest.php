<?php

namespace Tests\Feature\Depreciation;

use App\Models\Asset;
use App\Models\AssetDepreciationSetting;
use App\Models\CategoryDepreciationDefault;
use App\Models\DepreciationMethod;
use App\Models\DepreciationSettingRequest;
use App\Models\User;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\DepreciationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DepreciationServiceTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function straightLine(): DepreciationMethod
    {
        return DepreciationMethod::firstOrCreate(
            ['code' => 'straight_line'],
            ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true],
        );
    }

    private function service(): DepreciationService
    {
        return app(DepreciationService::class);
    }

    public function test_resolve_defaults_is_null_without_a_category_default(): void
    {
        $asset = Asset::factory()->create(['purchase_cost' => 100000, 'purchase_date' => '2024-01-01']);

        $this->assertNull($this->service()->resolveDefaults($asset));
    }

    public function test_resolve_defaults_inherits_from_category(): void
    {
        $method = $this->straightLine();
        $asset = Asset::factory()->create(['purchase_cost' => 100000, 'purchase_date' => '2024-01-01']);

        CategoryDepreciationDefault::create([
            'category_id'            => $asset->category_id,
            'depreciation_method_id' => $method->id,
            'useful_life_months'     => 60,
            'salvage_percent'        => 5,
            'start_basis'            => 'purchase_date',
        ]);

        $defaults = $this->service()->resolveDefaults($asset->fresh());

        $this->assertSame($method->id, $defaults['depreciation_method_id']);
        $this->assertSame(60, $defaults['useful_life_months']);
        $this->assertSame('2024-01-01', $defaults['start_date']);
        $this->assertEqualsWithDelta(100000, $defaults['cost_basis'], 0.01);
    }

    public function test_compute_salvage_honours_fixed_then_percent_then_default(): void
    {
        $svc = $this->service();

        $this->assertEqualsWithDelta(7000, $svc->computeSalvage(100000, 7000, 10), 0.01); // fixed wins
        $this->assertEqualsWithDelta(10000, $svc->computeSalvage(100000, null, 10), 0.01); // percent
        $this->assertEqualsWithDelta(5000, $svc->computeSalvage(100000, null, null), 0.01); // 5% default
    }

    public function test_apply_setting_change_creates_active_setting_schedule_and_computes_salvage(): void
    {
        $method = $this->straightLine();
        $user = User::factory()->create();
        $asset = Asset::factory()->create(['purchase_cost' => 100000, 'purchase_date' => '2024-01-01']);

        $request = DepreciationSettingRequest::create([
            'asset_id'               => $asset->id,
            'depreciation_method_id' => $method->id,
            'useful_life_months'     => 60,
            'salvage_percent'        => 5,
            'start_date'             => '2024-01-01',
            'cost_basis'             => 100000,
            'change_type'            => 'initial',
            'status'                 => 'pending_approval',
            'requested_by'           => $user->id,
        ]);

        $setting = $this->service()->applySettingChange($request);

        $this->assertTrue($setting->is_active);
        $this->assertEqualsWithDelta(5000, (float) $setting->salvage_value, 0.01);
        $this->assertSame('applied', $request->fresh()->status);
        $this->assertSame(60, $setting->scheduleLines()->count());
        $this->assertNotNull($asset->fresh()->activeDepreciationSetting());
    }

    public function test_superseding_a_setting_deactivates_the_previous_one(): void
    {
        $method = $this->straightLine();
        $user = User::factory()->create();
        $asset = Asset::factory()->create(['purchase_cost' => 100000, 'purchase_date' => '2024-01-01']);

        $makeRequest = fn (int $life) => DepreciationSettingRequest::create([
            'asset_id'               => $asset->id,
            'depreciation_method_id' => $method->id,
            'useful_life_months'     => $life,
            'salvage_percent'        => 5,
            'start_date'             => '2024-01-01',
            'cost_basis'             => 100000,
            'change_type'            => 'revision',
            'status'                 => 'pending_approval',
            'requested_by'           => $user->id,
        ]);

        $first = $this->service()->applySettingChange($makeRequest(60));
        $second = $this->service()->applySettingChange($makeRequest(48));

        $this->assertFalse($first->fresh()->is_active);
        $this->assertSame($second->id, $first->fresh()->superseded_by);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(1, AssetDepreciationSetting::where('asset_id', $asset->id)->where('is_active', true)->count());
    }

    public function test_post_due_periods_is_idempotent_and_syncs_book_value(): void
    {
        $svc = $this->service();
        $method = $this->straightLine();
        $asset = Asset::factory()->create();

        $setting = AssetDepreciationSetting::create([
            'asset_id'                 => $asset->id,
            'depreciation_method_id'   => $method->id,
            'useful_life_months'       => 60,
            'salvage_value'            => 0,
            'start_date'               => now()->subMonths(24)->startOfMonth()->toDateString(),
            'cost_basis'               => 120000,
            'accumulated_depreciation' => 0,
            'current_book_value'       => 120000,
            'is_active'                => true,
        ]);

        $svc->generateSchedule($setting);

        $firstRun = $svc->postDuePeriods();
        $secondRun = $svc->postDuePeriods();

        $this->assertGreaterThan(0, $firstRun);
        $this->assertSame(0, $secondRun); // nothing new due — idempotent

        $setting->refresh();
        $latest = $setting->scheduleLines()->where('status', 'posted')->orderByDesc('period_year')->orderByDesc('period_month')->first();
        $this->assertEqualsWithDelta((float) $latest->closing_book_value, (float) $setting->current_book_value, 0.01);
    }
}
