<?php

namespace Tests\Feature\Depreciation;

use App\Models\Asset;
use App\Models\AssetDepreciationSetting;
use App\Models\DepreciationMethod;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\DepreciationService;
use App\Services\Reports\ReportRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DepreciationReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function seedSchedule(): Asset
    {
        $asset = Asset::factory()->create();

        $setting = AssetDepreciationSetting::create([
            'asset_id'                 => $asset->id,
            'depreciation_method_id'   => DepreciationMethod::firstOrCreate(
                ['code' => 'straight_line'],
                ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true],
            )->id,
            'useful_life_months'       => 60,
            'salvage_value'            => 0,
            'start_date'               => now()->subMonths(12)->startOfMonth()->toDateString(),
            'cost_basis'               => 120000,
            'accumulated_depreciation' => 0,
            'current_book_value'       => 120000,
            'is_active'                => true,
        ]);

        app(DepreciationService::class)->generateSchedule($setting);
        app(DepreciationService::class)->postDuePeriods();

        return $asset;
    }

    public function test_depreciation_report_is_now_enabled(): void
    {
        $this->assertTrue(ReportRegistry::isEnabled('depreciation_schedule'));
    }

    public function test_report_renders_schedule_rows(): void
    {
        $asset = $this->seedSchedule();
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)
            ->get(route('reports.show', 'depreciation_schedule'))
            ->assertOk()
            ->assertSee($asset->name);
    }

    public function test_report_exports_inline(): void
    {
        $this->seedSchedule();
        $admin = $this->createUserWithRole('Super Admin');

        $response = $this->actingAs($admin)->post(route('reports.export', 'depreciation_schedule'), ['format' => 'xlsx']);

        $response->assertOk();
        $this->assertStringContainsString('depreciation_schedule-report', $response->headers->get('content-disposition') ?? '');
    }
}
