<?php

namespace Tests\Feature\Reports;

use App\Models\Asset;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AgingReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_screen_respects_the_company_filter_and_buckets_by_age(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Asset::factory()->create([
            'name' => 'Old Asset A', 'company_id' => $companyA->id, 'purchase_date' => now()->subYears(6),
        ]);
        Asset::factory()->create([
            'name' => 'Asset B', 'company_id' => $companyB->id, 'purchase_date' => now()->subYears(6),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'aging', 'company_id' => $companyA->id]));

        $response->assertOk();
        $response->assertSee('Old Asset A');
        $response->assertSee('5y+');
        $response->assertDontSee('Asset B');
    }

    public function test_assets_without_a_purchase_date_are_excluded(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        Asset::factory()->create(['name' => 'Undated Asset', 'purchase_date' => null]);

        $response = $this->actingAs($admin)->get(route('reports.show', 'aging'));

        $response->assertOk();
        $response->assertDontSee('Undated Asset');
    }

    public function test_export_row_count_matches_the_on_screen_filtered_set(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Asset::factory()->count(2)->create(['company_id' => $companyA->id, 'purchase_date' => now()->subYear()]);
        Asset::factory()->count(3)->create(['company_id' => $companyB->id, 'purchase_date' => now()->subYear()]);

        $this->actingAs($admin)
            ->post(route('reports.export', 'aging'), ['company_id' => $companyA->id])
            ->assertOk();

        Excel::assertDownloaded('/^aging-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 2);
    }
}
