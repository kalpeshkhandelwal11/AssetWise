<?php

namespace Tests\Feature\Reports;

use App\Models\AmcContract;
use App\Models\Asset;
use App\Models\WarrantyRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AmcWarrantyReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_both_amc_and_warranty_rows_appear_in_one_result_set(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create(['name' => 'Covered Asset']);

        AmcContract::factory()->create(['asset_id' => $asset->id, 'vendor' => 'AmcVendorX']);
        WarrantyRecord::factory()->create(['asset_id' => $asset->id, 'provider' => 'WarrantyProviderY']);

        $response = $this->actingAs($admin)->get(route('reports.show', ['type' => 'amc_warranty']));

        $response->assertOk();
        $response->assertSee('AmcVendorX');
        $response->assertSee('WarrantyProviderY');
    }

    public function test_kind_filter_isolates_amc_from_warranty(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create();

        AmcContract::factory()->create(['asset_id' => $asset->id, 'vendor' => 'OnlyAmcVendor']);
        WarrantyRecord::factory()->create(['asset_id' => $asset->id, 'provider' => 'OnlyWarrantyProvider']);

        $response = $this->actingAs($admin)->get(route('reports.show', ['type' => 'amc_warranty', 'kind' => 'amc']));

        $response->assertOk();
        $response->assertSee('OnlyAmcVendor');
        $response->assertDontSee('OnlyWarrantyProvider');
    }

    public function test_expiry_status_buckets_at_the_30_day_boundary(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $expiredAsset = Asset::factory()->create();
        $expiringAsset = Asset::factory()->create();
        $activeAsset = Asset::factory()->create();

        AmcContract::factory()->create(['asset_id' => $expiredAsset->id, 'vendor' => 'ExpiredVendor', 'end_date' => now()->subDays(5)]);
        AmcContract::factory()->create(['asset_id' => $expiringAsset->id, 'vendor' => 'ExpiringVendor', 'end_date' => now()->addDays(10)]);
        AmcContract::factory()->create(['asset_id' => $activeAsset->id, 'vendor' => 'ActiveVendor', 'end_date' => now()->addDays(90)]);

        $expired = $this->actingAs($admin)->get(route('reports.show', ['type' => 'amc_warranty', 'expiry_status' => 'expired']));
        $expired->assertOk();
        $expired->assertSee('ExpiredVendor');
        $expired->assertDontSee('ExpiringVendor');
        $expired->assertDontSee('ActiveVendor');

        $expiring = $this->actingAs($admin)->get(route('reports.show', ['type' => 'amc_warranty', 'expiry_status' => 'expiring']));
        $expiring->assertOk();
        $expiring->assertSee('ExpiringVendor');
        $expiring->assertDontSee('ExpiredVendor');
        $expiring->assertDontSee('ActiveVendor');

        $active = $this->actingAs($admin)->get(route('reports.show', ['type' => 'amc_warranty', 'expiry_status' => 'active']));
        $active->assertOk();
        $active->assertSee('ActiveVendor');
        $active->assertDontSee('ExpiredVendor');
        $active->assertDontSee('ExpiringVendor');
    }

    public function test_days_remaining_for_an_expired_contract_is_negative_not_a_crash(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create();
        AmcContract::factory()->create(['asset_id' => $asset->id, 'end_date' => now()->subDays(10)]);

        $this->actingAs($admin)
            ->post(route('reports.export', 'amc_warranty'))
            ->assertOk();

        Excel::assertDownloaded('/^amc_warranty-report-.*\.xlsx$/', function ($export) {
            $rows = $export->collection();
            $row = $export->map($rows->first());

            // Days Remaining is index 7 in AmcWarrantyExport::HEADINGS
            return $row[7] < 0;
        });
    }

    public function test_search_matches_provider_name_or_asset_name(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create(['name' => 'Searchable Laptop']);
        $otherAsset = Asset::factory()->create(['name' => 'Other Asset']);

        WarrantyRecord::factory()->create(['asset_id' => $asset->id, 'provider' => 'ZZZ Provider']);
        WarrantyRecord::factory()->create(['asset_id' => $otherAsset->id, 'provider' => 'Different Provider']);

        $response = $this->actingAs($admin)->get(route('reports.show', ['type' => 'amc_warranty', 'search' => 'Searchable']));

        $response->assertOk();
        $response->assertSee('Searchable Laptop');
        $response->assertDontSee('Other Asset');
    }

    public function test_export_row_count_matches_the_on_screen_filtered_set(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create();

        AmcContract::factory()->create(['asset_id' => $asset->id]);
        WarrantyRecord::factory()->create(['asset_id' => $asset->id]);

        $this->actingAs($admin)
            ->post(route('reports.export', 'amc_warranty'))
            ->assertOk();

        Excel::assertDownloaded('/^amc_warranty-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 2);
    }
}
