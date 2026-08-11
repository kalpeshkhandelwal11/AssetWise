<?php

namespace Tests\Feature\Reports;

use App\Models\Asset;
use App\Models\AssetStatusHistory;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AuditComplianceReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_screen_respects_the_company_filter_via_the_related_asset(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $assetA = Asset::factory()->create(['company_id' => $companyA->id, 'name' => 'Tracked A']);
        $assetB = Asset::factory()->create(['company_id' => $companyB->id, 'name' => 'Tracked B']);

        AssetStatusHistory::create(['asset_id' => $assetA->id, 'to_status_id' => $assetA->status_id, 'created_at' => now()]);
        AssetStatusHistory::create(['asset_id' => $assetB->id, 'to_status_id' => $assetB->status_id, 'created_at' => now()]);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'audit_compliance', 'company_id' => $companyA->id]));

        $response->assertOk();
        $response->assertSee('Tracked A');
        $response->assertDontSee('Tracked B');
    }

    public function test_export_row_count_matches_the_on_screen_filtered_set(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $assetA = Asset::factory()->create(['company_id' => $companyA->id]);
        $assetB = Asset::factory()->create(['company_id' => $companyB->id]);

        AssetStatusHistory::create(['asset_id' => $assetA->id, 'to_status_id' => $assetA->status_id, 'created_at' => now()]);
        AssetStatusHistory::create(['asset_id' => $assetA->id, 'to_status_id' => $assetA->status_id, 'created_at' => now()]);
        AssetStatusHistory::create(['asset_id' => $assetB->id, 'to_status_id' => $assetB->status_id, 'created_at' => now()]);

        $this->actingAs($admin)
            ->post(route('reports.export', 'audit_compliance'), ['company_id' => $companyA->id])
            ->assertOk();

        Excel::assertDownloaded('/^audit_compliance-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 2);
    }
}
