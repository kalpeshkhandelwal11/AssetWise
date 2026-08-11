<?php

namespace Tests\Feature\Audit;

use App\Models\Asset;
use App\Models\AuditCampaign;
use App\Models\AuditItem;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AuditCampaignReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_per_campaign_report_page_renders(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $campaign = AuditCampaign::factory()->create(['name' => 'Q3 Audit']);
        $asset = Asset::factory()->create(['name' => 'Tracked Asset']);
        AuditItem::factory()->create(['campaign_id' => $campaign->id, 'asset_id' => $asset->id, 'status' => 'verified']);

        $this->actingAs($manager)->get(route('audits.campaigns.report', $campaign))
            ->assertOk()
            ->assertSee('Q3 Audit')
            ->assertSee('Tracked Asset');
    }

    public function test_per_campaign_report_exports_xlsx_and_pdf(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $campaign = AuditCampaign::factory()->create();
        AuditItem::factory()->create(['campaign_id' => $campaign->id]);

        $this->actingAs($manager)
            ->post(route('audits.campaigns.report.export', $campaign), ['format' => 'xlsx'])
            ->assertOk();

        $this->actingAs($manager)
            ->post(route('audits.campaigns.report.export', $campaign), ['format' => 'pdf'])
            ->assertOk();
    }

    public function test_audit_campaign_registry_entry_is_enabled(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)->get(route('reports.show', 'audit_campaign'))->assertOk();
    }

    public function test_registry_report_respects_campaign_filter(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $company = Company::factory()->create();
        $campaignA = AuditCampaign::factory()->create(['name' => 'Campaign A']);
        $campaignB = AuditCampaign::factory()->create(['name' => 'Campaign B']);
        $assetA = Asset::factory()->create(['company_id' => $company->id, 'name' => 'Asset In A']);
        $assetB = Asset::factory()->create(['name' => 'Asset In B']);
        AuditItem::factory()->create(['campaign_id' => $campaignA->id, 'asset_id' => $assetA->id]);
        AuditItem::factory()->create(['campaign_id' => $campaignB->id, 'asset_id' => $assetB->id]);

        $response = $this->actingAs($admin)
            ->get(route('reports.show', ['type' => 'audit_campaign', 'campaign_id' => $campaignA->id]));

        $response->assertOk();
        $response->assertSee('Asset In A');
        $response->assertDontSee('Asset In B');
    }

    public function test_registry_report_export_row_count_matches_filtered_set(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $campaignA = AuditCampaign::factory()->create();
        $campaignB = AuditCampaign::factory()->create();
        AuditItem::factory()->count(2)->create(['campaign_id' => $campaignA->id]);
        AuditItem::factory()->create(['campaign_id' => $campaignB->id]);

        $this->actingAs($admin)
            ->post(route('reports.export', 'audit_campaign'), ['campaign_id' => $campaignA->id])
            ->assertOk();

        Excel::assertDownloaded('/^audit_campaign-report-.*\.xlsx$/', fn ($export) => $export->rowCount() === 2);
    }
}
