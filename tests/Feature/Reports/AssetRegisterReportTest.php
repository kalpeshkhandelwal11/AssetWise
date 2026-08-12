<?php

namespace Tests\Feature\Reports;

use App\Models\Asset;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AssetRegisterReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_screen_respects_the_company_filter(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        Asset::factory()->create(['name' => 'Asset A', 'company_id' => $companyA->id]);
        Asset::factory()->create(['name' => 'Asset B', 'company_id' => $companyB->id]);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'asset_register', 'company_id' => $companyA->id]));

        $response->assertOk();
        $response->assertSee('Asset A');
        $response->assertDontSee('Asset B');
    }

    public function test_export_row_count_matches_the_on_screen_filtered_set(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        Asset::factory()->count(2)->create(['company_id' => $companyA->id]);
        Asset::factory()->count(3)->create(['company_id' => $companyB->id]);

        $this->actingAs($admin)
            ->post(route('reports.export', 'asset_register'), ['company_id' => $companyA->id])
            ->assertOk();

        Excel::assertDownloaded('/^asset_register-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 2);
    }

    public function test_screen_shows_eol_status_and_projected_date(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        Asset::factory()->create(['name' => 'EOL Asset', 'is_eol' => true, 'eol_projected_date' => '2027-01-15']);

        $response = $this->actingAs($admin)->get(route('reports.show', ['type' => 'asset_register']));

        $response->assertOk();
        $response->assertSee('EOL Asset');
        $response->assertSee('EOL');
        $response->assertSee('15 Jan 2027');
    }

    public function test_export_includes_eol_columns(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        Asset::factory()->create(['is_eol' => true, 'eol_projected_date' => '2027-01-15']);

        $this->actingAs($admin)->post(route('reports.export', 'asset_register'))->assertOk();

        Excel::assertDownloaded('/^asset_register-report-.*\.xlsx$/', function ($export) {
            $headings = $export->headings();
            $this->assertContains('End of Life', $headings);
            $this->assertContains('EOL Projected Date', $headings);

            $row = $export->map($export->collection()->first());
            $eolIndex = array_search('End of Life', $headings, true);
            $eolDateIndex = array_search('EOL Projected Date', $headings, true);

            return $row[$eolIndex] === 'Yes' && $row[$eolDateIndex] === '2027-01-15';
        });
    }
}
