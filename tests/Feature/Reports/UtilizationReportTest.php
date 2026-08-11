<?php

namespace Tests\Feature\Reports;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UtilizationReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_screen_shows_assigned_vs_available_per_company(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $company = Company::factory()->create(['name' => 'Acme Corp']);

        $assigned = AssetStatus::create(['name' => 'Assigned', 'code' => 'ASSIGNED', 'color' => '#000', 'is_system' => true, 'is_active' => true]);
        $available = AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#000', 'is_system' => true, 'is_active' => true]);

        Asset::factory()->count(3)->create(['company_id' => $company->id, 'status_id' => $assigned->id]);
        Asset::factory()->count(1)->create(['company_id' => $company->id, 'status_id' => $available->id]);

        $response = $this->actingAs($admin)->get(route('reports.show', ['type' => 'utilization', 'company_id' => $company->id]));

        $response->assertOk();
        $response->assertSee('Acme Corp');
        $response->assertSee('75'); // 3 of 4 assigned = 75% utilization
    }

    public function test_export_row_count_matches_the_company_filter(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        Company::factory()->create();

        Asset::factory()->create(['company_id' => $companyA->id]);

        $this->actingAs($admin)
            ->post(route('reports.export', 'utilization'), ['company_id' => $companyA->id])
            ->assertOk();

        Excel::assertDownloaded('/^utilization-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 1);
    }
}
