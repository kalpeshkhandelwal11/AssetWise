<?php

namespace Tests\Feature\Reports;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MovementReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_screen_respects_the_company_filter_on_either_from_or_to_company(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $assetA = Asset::factory()->create(['company_id' => $companyA->id, 'name' => 'Laptop A']);
        $assetB = Asset::factory()->create(['company_id' => $companyB->id, 'name' => 'Laptop B']);

        AssetMovement::factory()->create(['asset_id' => $assetA->id, 'from_company_id' => $companyA->id]);
        AssetMovement::factory()->create(['asset_id' => $assetB->id, 'from_company_id' => $companyB->id]);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'movement', 'company_id' => $companyA->id]));

        $response->assertOk();
        $response->assertSee('Laptop A');
        $response->assertDontSee('Laptop B');
    }

    public function test_export_row_count_matches_the_on_screen_filtered_set(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        AssetMovement::factory()->count(2)->create(['from_company_id' => $companyA->id]);
        AssetMovement::factory()->count(3)->create(['from_company_id' => $companyB->id]);

        $this->actingAs($admin)
            ->post(route('reports.export', 'movement'), ['company_id' => $companyA->id])
            ->assertOk();

        Excel::assertDownloaded('/^movement-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 2);
    }
}
