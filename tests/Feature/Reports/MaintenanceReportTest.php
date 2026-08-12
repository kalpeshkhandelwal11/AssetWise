<?php

namespace Tests\Feature\Reports;

use App\Models\Asset;
use App\Models\Company;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MaintenanceReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_screen_respects_the_company_filter_via_the_related_asset(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $assetA = Asset::factory()->create(['company_id' => $companyA->id, 'name' => 'Laptop A']);
        $assetB = Asset::factory()->create(['company_id' => $companyB->id, 'name' => 'Laptop B']);

        MaintenanceRecord::factory()->create(['asset_id' => $assetA->id, 'status' => 'completed', 'performed_date' => now()]);
        MaintenanceRecord::factory()->create(['asset_id' => $assetB->id, 'status' => 'completed', 'performed_date' => now()]);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'maintenance', 'company_id' => $companyA->id]));

        $response->assertOk();
        $response->assertSee('Laptop A');
        $response->assertDontSee('Laptop B');
    }

    public function test_maintenance_type_filter(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $preventive = MaintenanceType::firstOrCreate(['code' => 'PREVENTIVE'], ['name' => 'Preventive', 'is_active' => true]);
        $corrective = MaintenanceType::create(['name' => 'Corrective', 'code' => 'CORRECTIVE', 'is_active' => true]);

        $assetPrev = Asset::factory()->create(['name' => 'Prev Asset']);
        $assetCorr = Asset::factory()->create(['name' => 'Corr Asset']);

        MaintenanceRecord::factory()->create(['asset_id' => $assetPrev->id, 'maintenance_type_id' => $preventive->id, 'status' => 'completed', 'performed_date' => now()]);
        MaintenanceRecord::factory()->create(['asset_id' => $assetCorr->id, 'maintenance_type_id' => $corrective->id, 'status' => 'completed', 'performed_date' => now()]);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'maintenance', 'maintenance_type_id' => $corrective->id]));

        $response->assertOk();
        $response->assertSee('Corr Asset');
        $response->assertDontSee('Prev Asset');
    }

    public function test_is_capitalized_filter(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $capAsset = Asset::factory()->create(['name' => 'Capitalized Asset']);
        $plainAsset = Asset::factory()->create(['name' => 'Plain Asset']);

        MaintenanceRecord::factory()->create([
            'asset_id' => $capAsset->id, 'status' => 'completed', 'performed_date' => now(),
            'is_capitalized' => true, 'capitalized_amount' => 5000, 'additional_useful_life_months' => 12,
        ]);
        MaintenanceRecord::factory()->create(['asset_id' => $plainAsset->id, 'status' => 'completed', 'performed_date' => now(), 'is_capitalized' => false]);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'maintenance', 'is_capitalized' => '1']));

        $response->assertOk();
        $response->assertSee('Capitalized Asset');
        $response->assertDontSee('Plain Asset');
    }

    public function test_performed_date_range_excludes_scheduled_records_with_no_performed_date(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $performedAsset = Asset::factory()->create(['name' => 'Performed Asset']);
        $scheduledAsset = Asset::factory()->create(['name' => 'Scheduled Only Asset']);

        MaintenanceRecord::factory()->create([
            'asset_id' => $performedAsset->id, 'status' => 'completed', 'performed_date' => now()->subDays(2),
        ]);
        MaintenanceRecord::factory()->create([
            'asset_id' => $scheduledAsset->id, 'status' => 'scheduled', 'performed_date' => null, 'scheduled_date' => now()->addDays(5),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.show', [
            'type' => 'maintenance',
            'date_from' => now()->subDays(5)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Performed Asset');
        $response->assertDontSee('Scheduled Only Asset');
    }

    public function test_export_row_count_matches_the_on_screen_filtered_set(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $assetsA = Asset::factory()->count(2)->create(['company_id' => $companyA->id]);
        $assetsA->each(fn ($asset) => MaintenanceRecord::factory()->create(['asset_id' => $asset->id, 'status' => 'completed', 'performed_date' => now()]));

        $assetsB = Asset::factory()->count(3)->create(['company_id' => $companyB->id]);
        $assetsB->each(fn ($asset) => MaintenanceRecord::factory()->create(['asset_id' => $asset->id, 'status' => 'completed', 'performed_date' => now()]));

        $this->actingAs($admin)
            ->post(route('reports.export', 'maintenance'), ['company_id' => $companyA->id])
            ->assertOk();

        Excel::assertDownloaded('/^maintenance-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 2);
    }
}
