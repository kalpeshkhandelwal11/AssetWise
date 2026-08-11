<?php

namespace Tests\Feature\Reports;

use App\Models\Asset;
use App\Models\Company;
use App\Models\DisposalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DisposalReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_screen_respects_the_company_filter_via_the_related_asset(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $assetA = Asset::factory()->create(['company_id' => $companyA->id, 'name' => 'Scrap A']);
        $assetB = Asset::factory()->create(['company_id' => $companyB->id, 'name' => 'Scrap B']);

        DisposalRequest::factory()->create(['asset_id' => $assetA->id]);
        DisposalRequest::factory()->create(['asset_id' => $assetB->id]);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'disposal', 'company_id' => $companyA->id]));

        $response->assertOk();
        $response->assertSee('Scrap A');
        $response->assertDontSee('Scrap B');
    }

    public function test_export_row_count_matches_the_on_screen_filtered_set(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $assetsA = Asset::factory()->count(2)->create(['company_id' => $companyA->id]);
        $assetsA->each(fn ($asset) => DisposalRequest::factory()->create(['asset_id' => $asset->id]));

        $assetsB = Asset::factory()->count(3)->create(['company_id' => $companyB->id]);
        $assetsB->each(fn ($asset) => DisposalRequest::factory()->create(['asset_id' => $asset->id]));

        $this->actingAs($admin)
            ->post(route('reports.export', 'disposal'), ['company_id' => $companyA->id])
            ->assertOk();

        Excel::assertDownloaded('/^disposal-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 2);
    }
}
