<?php

namespace Tests\Feature\Audit;

use App\Models\Asset;
use App\Models\AuditCampaign;
use App\Models\AuditType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CampaignControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function auditType(): AuditType
    {
        return AuditType::firstOrCreate(['code' => 'PHYSICAL'], ['name' => 'Physical', 'is_active' => true]);
    }

    public function test_index_requires_audit_manage_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('audits.campaigns.index'))->assertForbidden();
    }

    public function test_index_is_accessible_to_asset_manager(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)->get(route('audits.campaigns.index'))->assertOk();
    }

    public function test_store_creates_a_draft_campaign_with_auditors(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $asset = Asset::factory()->create();

        $response = $this->actingAs($manager)->post(route('audits.campaigns.store'), [
            'name'          => 'Q3 Physical Audit',
            'audit_type_id' => $this->auditType()->id,
            'start_date'    => now()->toDateString(),
            'scope'         => ['company_id' => $asset->company_id],
            'auditor_ids'   => [$auditor->id],
        ]);

        $campaign = AuditCampaign::firstOrFail();
        $response->assertRedirect(route('audits.campaigns.show', $campaign));
        $this->assertSame('draft', $campaign->status);
        $this->assertTrue($campaign->hasAuditor($auditor));
    }

    public function test_update_is_rejected_once_campaign_is_active(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $asset = Asset::factory()->create();
        $campaign = AuditCampaign::factory()->create([
            'scope' => ['company_id' => $asset->company_id],
        ]);
        $campaign->auditors()->sync([$auditor->id]);
        $campaign->update(['status' => 'active']);

        $response = $this->actingAs($manager)->put(route('audits.campaigns.update', $campaign), [
            'name'          => 'Renamed',
            'audit_type_id' => $this->auditType()->id,
            'start_date'    => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertNotSame('Renamed', $campaign->fresh()->name);
    }

    public function test_edit_redirects_away_once_campaign_is_active(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $asset = Asset::factory()->create();
        $campaign = AuditCampaign::factory()->create(['scope' => ['company_id' => $asset->company_id]]);
        $campaign->auditors()->sync([$auditor->id]);
        $campaign->update(['status' => 'active']);

        $this->actingAs($manager)->get(route('audits.campaigns.edit', $campaign))
            ->assertRedirect(route('audits.campaigns.show', $campaign));
    }

    public function test_destroy_deletes_a_draft_campaign(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $campaign = AuditCampaign::factory()->create();

        $this->actingAs($manager)->delete(route('audits.campaigns.destroy', $campaign))
            ->assertRedirect(route('audits.campaigns.index'));

        $this->assertDatabaseMissing('audit_campaigns', ['id' => $campaign->id]);
    }

    public function test_destroy_is_rejected_once_campaign_is_active(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $asset = Asset::factory()->create();
        $campaign = AuditCampaign::factory()->create(['scope' => ['company_id' => $asset->company_id]]);
        $campaign->auditors()->sync([$auditor->id]);
        $campaign->update(['status' => 'active']);

        $this->actingAs($manager)->delete(route('audits.campaigns.destroy', $campaign))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('audit_campaigns', ['id' => $campaign->id]);
    }

    public function test_create_form_renders(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)->get(route('audits.campaigns.create'))
            ->assertOk()
            ->assertSee('New Audit Campaign');
    }

    public function test_edit_form_renders_for_a_draft_campaign(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $campaign = AuditCampaign::factory()->create(['name' => 'Edit Me']);
        $campaign->auditors()->sync([$auditor->id]);

        $this->actingAs($manager)->get(route('audits.campaigns.edit', $campaign))
            ->assertOk()
            ->assertSee('Edit Me');
    }

    public function test_show_renders_a_draft_campaign_with_matching_asset_count(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $campaign = AuditCampaign::factory()->create(['scope' => ['company_id' => $asset->company_id]]);

        $this->actingAs($manager)->get(route('audits.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('1')
            ->assertSee('Activate Campaign');
    }

    public function test_show_renders_an_active_campaign_with_progress_stats(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $asset = Asset::factory()->create();
        $campaign = AuditCampaign::factory()->create(['scope' => ['company_id' => $asset->company_id]]);
        $campaign->auditors()->sync([$auditor->id]);
        app(\App\Services\AuditService::class)->activate($campaign, $manager);

        $this->actingAs($manager)->get(route('audits.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Verification progress')
            ->assertSee('Close Campaign');
    }
}
