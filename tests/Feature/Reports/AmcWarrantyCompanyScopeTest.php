<?php

namespace Tests\Feature\Reports;

use App\Models\AmcContract;
use App\Models\Asset;
use App\Models\Company;
use App\Models\WarrantyRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Regression test for a real bug found while planning this report (docs/decisions-log.md,
 * M14 pending reports): buildAmcWarrantyQuery() UNIONs amc_contracts and warranty_records.
 * A ->whereHas('asset', ...) company filter applied to the builder BEFORE ->union() only
 * constrains the first leg — rows from the unioned (second) leg sail through completely
 * unfiltered. That is a cross-company data leak on a report whose whole purpose is
 * company-scoped visibility. This test fails against the naive single-filter
 * implementation and passes only when the filter is applied to both legs independently.
 */
class AmcWarrantyCompanyScopeTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_company_filter_excludes_the_other_companys_warranty_row_from_the_unioned_leg(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $assetA = Asset::factory()->create(['company_id' => $companyA->id]);
        $assetB = Asset::factory()->create(['company_id' => $companyB->id]);

        AmcContract::factory()->create(['asset_id' => $assetA->id, 'vendor' => 'CompanyA-AMC']);
        WarrantyRecord::factory()->create(['asset_id' => $assetA->id, 'provider' => 'CompanyA-Warranty']);
        AmcContract::factory()->create(['asset_id' => $assetB->id, 'vendor' => 'CompanyB-AMC']);
        WarrantyRecord::factory()->create(['asset_id' => $assetB->id, 'provider' => 'CompanyB-Warranty']);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'amc_warranty', 'company_id' => $companyA->id]));

        $response->assertOk();
        $response->assertSee('CompanyA-AMC');
        $response->assertSee('CompanyA-Warranty');
        $response->assertDontSee('CompanyB-AMC');
        // The specific assertion that catches the leak: the naive implementation lets
        // exactly this row (the unioned/second leg's other-company row) through.
        $response->assertDontSee('CompanyB-Warranty');
    }

    public function test_company_filter_yields_exactly_the_expected_row_count_via_export(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $assetA = Asset::factory()->create(['company_id' => $companyA->id]);
        $assetB = Asset::factory()->create(['company_id' => $companyB->id]);

        AmcContract::factory()->create(['asset_id' => $assetA->id]);
        WarrantyRecord::factory()->create(['asset_id' => $assetA->id]);
        AmcContract::factory()->create(['asset_id' => $assetB->id]);
        WarrantyRecord::factory()->create(['asset_id' => $assetB->id]);

        $this->actingAs($admin)
            ->post(route('reports.export', 'amc_warranty'), ['company_id' => $companyA->id])
            ->assertOk();

        // Naive (filter on one leg only) would return 3 here: A's AMC, A's warranty, and
        // B's warranty (the unfiltered unioned leg). Correct is exactly 2.
        Excel::assertDownloaded('/^amc_warranty-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 2);
    }
}
