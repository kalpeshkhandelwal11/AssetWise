<?php

namespace Tests\Feature\Reports;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\DepreciationClass;
use App\Models\DepreciationMethod;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\DepreciationService;
use App\Services\Reports\DepreciationRegisterBuilder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The FY Depreciation Register reproduces the company's Schedule II register (FY 26-27):
 * life from the Schedule II class, 5% residual, days used inside the FY, "Add: Purchase" for
 * in-year additions, Land at cost with no depreciation, class subtotals and a grand total.
 */
class DepreciationRegisterTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function categoryFor(string $classCode): AssetCategory
    {
        $class = DepreciationClass::where('code', $classCode)->firstOrFail();
        $category = AssetCategory::factory()->create(['depreciation_class_id' => $class->id]);

        if ($class->is_depreciable) {
            $method = DepreciationMethod::firstOrCreate(
                ['code' => 'straight_line'],
                ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true],
            );
            $category->depreciationDefault()->create([
                'depreciation_method_id' => $method->id,
                'useful_life_months'     => $class->usefulLifeMonths(),
                'salvage_percent'        => 5,
                'start_basis'            => 'purchase_date',
            ]);
        }

        return $category;
    }

    public function test_register_matches_the_schedule_ii_sheet(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $electrical = $this->categoryFor('ELECTRICAL');
        $land = $this->categoryFor('LAND');

        // Row 13 of the FY 26-27 sheet: ₹26,000 Electrical Installation bought 13-Apr-2026.
        $ac = Asset::factory()->create([
            'name' => 'AIR CONDITION MACHINE', 'category_id' => $electrical->id,
            'purchase_cost' => 26000, 'purchase_date' => '2026-04-13',
        ]);
        app(DepreciationService::class)->applyCategoryDefault($ac);

        $plot = Asset::factory()->create([
            'name' => 'HIDCO 3255 LAND', 'category_id' => $land->id,
            'purchase_cost' => 19734000, 'purchase_date' => '2026-04-01',
        ]);
        $this->assertNull(app(DepreciationService::class)->applyCategoryDefault($plot), 'land is never depreciated');

        $rows = app(DepreciationRegisterBuilder::class)->rows(['fy' => 2026]);
        $acRow = $rows->firstWhere('description', 'AIR CONDITION MACHINE');
        $landRow = $rows->firstWhere('description', 'HIDCO 3255 LAND');

        $this->assertSame('Electrical Installation', $acRow['class']);
        $this->assertTrue($acRow['is_addition']);
        $this->assertSame(353, $acRow['days']);                     // 13-Apr → 31-Mar inclusive
        $this->assertEquals(1300.0, $acRow['residual']);            // 5%
        $this->assertEquals(10.0, $acRow['life_years']);
        $this->assertEquals(10.0, $acRow['rate']);
        $this->assertEqualsWithDelta(2388.79, $acRow['depreciation'], 5.0); // sheet: 2,388.79
        $this->assertEqualsWithDelta(26000 - $acRow['depreciation'], $acRow['closing_wdv'], 0.01);

        $this->assertEquals(0.0, $landRow['depreciation']);
        $this->assertEquals(19734000.0, $landRow['closing_wdv']);

        $total = $rows->firstWhere('kind', 'total');
        $this->assertEqualsWithDelta(26000 + 19734000, $total['cost'], 0.01);
        $this->assertCount(2, $rows->where('kind', 'subtotal'));
    }

    public function test_report_page_and_pdf_export_render(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $admin = $this->createUserWithRole('Super Admin');
        $category = $this->categoryFor('COMPUTERS');
        $laptop = Asset::factory()->create(['name' => 'LAPTOP', 'category_id' => $category->id, 'purchase_cost' => 71834, 'purchase_date' => '2026-04-01']);
        app(DepreciationService::class)->applyCategoryDefault($laptop);

        $this->actingAs($admin)->get(route('reports.show', ['type' => 'depreciation_register', 'fy' => 2026]))
            ->assertOk()->assertSee('LAPTOP')->assertSee('GRAND TOTAL');

        $this->actingAs($admin)->post(route('reports.export', 'depreciation_register'), ['fy' => 2026, 'format' => 'pdf'])
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
