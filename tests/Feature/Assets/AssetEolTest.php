<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\CategoryDepreciationDefault;
use App\Models\Company;
use App\Models\DepreciationMethod;
use App\Models\User;
use App\Services\Depreciation\StraightLineCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * End of Life is derived from the EOL date (no checkbox), and the asset form's life and
 * salvage are prefilled from the category and are what the first depreciation schedule uses.
 */
class AssetEolTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    private function baseFields(Asset $asset): array
    {
        return [
            'name'          => $asset->name,
            'category_id'   => $asset->category_id,
            'asset_type_id' => $asset->asset_type_id,
            'status_id'     => $asset->status_id,
        ];
    }

    // ------------------------------------------------------------------ End of Life

    public function test_end_of_life_follows_the_eol_date_not_a_checkbox(): void
    {
        $admin = $this->admin();
        $asset = Asset::factory()->create(['is_eol' => false, 'eol_projected_date' => null]);

        // A past date makes it End of Life, whatever is posted for is_eol.
        $this->actingAs($admin)->put(route('assets.update', $asset), $this->baseFields($asset) + [
            'is_eol' => '0', 'eol_projected_date' => now()->subDay()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertTrue($asset->fresh()->is_eol);

        // A future date means it is not End of Life yet.
        $this->actingAs($admin)->put(route('assets.update', $asset), $this->baseFields($asset) + [
            'is_eol' => '1', 'eol_projected_date' => now()->addYear()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertFalse($asset->fresh()->is_eol);
    }

    public function test_the_daily_sync_marks_assets_whose_date_has_arrived(): void
    {
        $due = Asset::factory()->create(['is_eol' => false, 'eol_projected_date' => now()->subDays(2)]);
        $later = Asset::factory()->create(['is_eol' => true, 'eol_projected_date' => now()->addMonth()]);
        $noDate = Asset::factory()->create(['is_eol' => true, 'eol_projected_date' => null]);

        $this->artisan('assets:sync-eol', ['--dry-run' => true])->expectsOutputToContain('Would mark 1');
        $this->assertFalse($due->fresh()->is_eol);

        $this->artisan('assets:sync-eol')->assertSuccessful();

        $this->assertTrue($due->fresh()->is_eol);
        $this->assertFalse($later->fresh()->is_eol);
        $this->assertFalse($noDate->fresh()->is_eol);

        $this->artisan('assets:sync-eol')->expectsOutputToContain('already match');
    }

    public function test_past_eol_filter_and_dashboard_count(): void
    {
        $admin = $this->admin();
        Asset::factory()->create(['name' => 'OLD-PRINTER', 'eol_projected_date' => now()->subMonth()]);
        Asset::factory()->create(['name' => 'NEW-PRINTER', 'eol_projected_date' => now()->addYears(3)]);

        $this->actingAs($admin)->get(route('assets.index', ['past_eol' => 1]))
            ->assertOk()->assertSee('OLD-PRINTER')->assertDontSee('NEW-PRINTER');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Past End of Life');
    }

    public function test_the_form_no_longer_has_an_end_of_life_checkbox(): void
    {
        $this->actingAs($this->admin())->get(route('assets.create'))
            ->assertOk()
            ->assertDontSee('name="is_eol"', false)
            ->assertSee('name="salvage_percent"', false);
    }

    // ------------------------------------------------------------------ life + salvage -> depreciation

    private function categoryWithDefault(int $months = 60, ?float $percent = 5): AssetCategory
    {
        $method = DepreciationMethod::firstOrCreate(['code' => 'straight_line'], ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true]);
        $category = AssetCategory::factory()->create();
        CategoryDepreciationDefault::create([
            'category_id' => $category->id, 'depreciation_method_id' => $method->id,
            'useful_life_months' => $months, 'salvage_percent' => $percent, 'start_basis' => 'purchase_date',
        ]);

        return $category;
    }

    private function createPayload(AssetCategory $category, array $extra = []): array
    {
        return $extra + [
            'name'          => 'Depreciating Laptop',
            'company_id'    => Company::factory()->create()->id,
            'category_id'   => $category->id,
            'asset_type_id' => AssetType::firstOrCreate(['code' => 'HW'], ['name' => 'HW', 'is_active' => true])->id,
            'status_id'     => AssetStatus::firstOrCreate(['code' => 'AVAILABLE'], ['name' => 'Available', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true])->id,
            'purchase_cost' => 100000,
            'purchase_date' => now()->subMonth()->toDateString(),
        ];
    }

    public function test_life_and_salvage_typed_on_the_form_drive_the_first_schedule(): void
    {
        $category = $this->categoryWithDefault(60, 5);

        $this->actingAs($this->admin())->post(route('assets.store'), $this->createPayload($category, [
            'useful_life_years' => 7.5, 'salvage_percent' => 10,
        ]))->assertSessionHasNoErrors();

        $setting = Asset::where('name', 'Depreciating Laptop')->firstOrFail()->activeDepreciationSetting();
        $this->assertSame(90, (int) $setting->useful_life_months);
        $this->assertEqualsWithDelta(10, (float) $setting->salvage_percent, 0.001);
        $this->assertEqualsWithDelta(10000, (float) $setting->salvage_value, 0.01);
    }

    public function test_blank_life_and_salvage_fall_back_to_the_category(): void
    {
        $category = $this->categoryWithDefault(48, 5);

        $this->actingAs($this->admin())->post(route('assets.store'), $this->createPayload($category))
            ->assertSessionHasNoErrors();

        $setting = Asset::where('name', 'Depreciating Laptop')->firstOrFail()->activeDepreciationSetting();
        $this->assertSame(48, (int) $setting->useful_life_months);
        $this->assertEqualsWithDelta(5000, (float) $setting->salvage_value, 0.01);
    }

    public function test_once_a_schedule_exists_life_and_salvage_are_not_editable_on_the_form(): void
    {
        $admin = $this->admin();
        $category = $this->categoryWithDefault(60, 5);
        $this->actingAs($admin)->post(route('assets.store'), $this->createPayload($category));
        $asset = Asset::where('name', 'Depreciating Laptop')->firstOrFail();

        $this->actingAs($admin)->put(route('assets.update', $asset), $this->baseFields($asset) + [
            'company_id' => $asset->company_id, 'purchase_cost' => 100000, 'purchase_date' => $asset->purchase_date->toDateString(),
            'useful_life_years' => 2, 'salvage_percent' => 50,
        ])->assertSessionHasNoErrors();

        $this->assertSame(60, (int) $asset->fresh()->activeDepreciationSetting()->useful_life_months);
        $this->assertNull($asset->fresh()->salvage_percent);

        $this->actingAs($admin)->get(route('assets.edit', $asset))
            ->assertOk()->assertSee("From this asset's depreciation schedule", false);
    }

    public function test_the_form_carries_each_categorys_defaults_for_prefill(): void
    {
        $category = $this->categoryWithDefault(18, 7.5);

        $this->actingAs($this->admin())->get(route('assets.create'))
            ->assertOk()
            ->assertSee('life_years', false)
            ->assertSee((string) $category->id, false);
    }
}
