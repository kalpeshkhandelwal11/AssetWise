<?php

namespace Tests\Feature\Depreciation;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\DepreciationMethod;
use App\Services\Depreciation\DepreciationInput;
use App\Services\Depreciation\StraightLineCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Salvage (residual) value is mandatory — a percent of cost OR a fixed amount, never both,
 * never neither — and the schedule depreciates only cost − salvage, closing exactly at salvage.
 */
class SalvageMandatoryTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function method(): DepreciationMethod
    {
        return DepreciationMethod::firstOrCreate(
            ['code' => 'straight_line'],
            ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true],
        );
    }

    private function assetPayload(array $salvage): array
    {
        return [
            'depreciation_method_id' => $this->method()->id,
            'useful_life_months'     => 60,
            'start_date'             => '2026-04-01',
            'cost_basis'             => 100000,
        ] + $salvage;
    }

    private function manager()
    {
        $this->seedRolesAndPermissions();
        $workflow = ApprovalWorkflow::factory()->module('depreciation')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);

        return $this->createUserWithRole('Asset Manager');
    }

    public function test_asset_override_requires_a_salvage_value(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->manager())
            ->put(route('assets.depreciation.update', $asset), $this->assetPayload([]))
            ->assertSessionHasErrors(['salvage_value', 'salvage_percent']);
    }

    public function test_percent_and_fixed_cannot_both_be_given(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->manager())
            ->put(route('assets.depreciation.update', $asset), $this->assetPayload(['salvage_percent' => 5, 'salvage_value' => 1000]))
            ->assertSessionHasErrors('salvage_value');
    }

    public function test_fixed_salvage_cannot_exceed_cost(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->manager())
            ->put(route('assets.depreciation.update', $asset), $this->assetPayload(['salvage_value' => 150000]))
            ->assertSessionHasErrors('salvage_value');
    }

    public function test_zero_salvage_is_an_explicit_valid_choice(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->manager())
            ->put(route('assets.depreciation.update', $asset), $this->assetPayload(['salvage_value' => 0]))
            ->assertSessionHasNoErrors();
    }

    public function test_category_default_requires_salvage(): void
    {
        $category = AssetCategory::factory()->create();
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)->put(route('admin.categories.depreciation.update', $category), [
            'depreciation_method_id' => $this->method()->id,
            'useful_life_months'     => 36,
            'start_basis'            => 'purchase_date',
        ])->assertSessionHasErrors(['salvage_value', 'salvage_percent']);

        $this->actingAs($admin)->put(route('admin.categories.depreciation.update', $category), [
            'depreciation_method_id' => $this->method()->id,
            'useful_life_months'     => 36,
            'start_basis'            => 'purchase_date',
            'salvage_percent'        => 5,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(5, $category->fresh()->depreciationDefault->salvage_percent);
    }

    public function test_schedule_depreciates_only_cost_minus_salvage_and_closes_at_salvage(): void
    {
        // ₹26,000 bought 13-Apr (a row of the company's FY26-27 register), 10-year life, 5% residual.
        $lines = (new StraightLineCalculator)->generate(new DepreciationInput(
            costBasis: 26000.0,
            salvageValue: 1300.0,
            usefulLifeMonths: 120,
            startDate: Carbon::parse('2026-04-13'),
        ));

        $total = array_sum(array_map(fn ($l) => $l->depreciationAmount, $lines));

        $this->assertEqualsWithDelta(24700.0, $total, 0.001, 'only cost − salvage is depreciated');
        $this->assertEqualsWithDelta(1300.0, end($lines)->closingBookValue, 0.001, 'closes exactly at salvage');

        foreach ($lines as $line) {
            $this->assertGreaterThanOrEqual(1300.0 - 0.001, $line->closingBookValue, 'never below salvage');
        }

        // First FY (13-Apr-2026 → 31-Mar-2027 = 353 days) ≈ the register's ₹2,388.79.
        $fy = array_sum(array_map(fn ($l) => $l->depreciationAmount, array_filter($lines,
            fn ($l) => ($l->periodYear === 2026 && $l->periodMonth >= 4) || ($l->periodYear === 2027 && $l->periodMonth <= 3))));
        $this->assertEqualsWithDelta(2388.79, $fy, 5.0);
    }
}
