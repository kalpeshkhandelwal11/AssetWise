<?php

namespace Tests\Feature\Audit;

use App\Models\Asset;
use App\Models\AuditCampaign;
use App\Models\AuditItem;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AuditScanVerificationTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_scanning_an_assigned_tag_as_the_assigned_auditor_routes_to_verification(): void
    {
        $auditor = $this->createUserWithRole('Auditor');
        $auditor->givePermissionTo('tags.view');
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();
        app(TagService::class)->assignToAsset($tag, $asset, $auditor);

        $campaign = AuditCampaign::factory()->create(['status' => 'active']);
        $campaign->auditors()->sync([$auditor->id]);
        $item = AuditItem::factory()->create(['campaign_id' => $campaign->id, 'asset_id' => $asset->id]);

        $this->actingAs($auditor)
            ->get(route('scan.resolve', $tag->fresh()->tag_number))
            ->assertRedirect(route('audits.verify', ['campaign' => $campaign->id, 'item' => $item->id]));
    }

    public function test_scanning_the_same_tag_without_audit_verify_permission_goes_to_asset_detail(): void
    {
        $manager = $this->createUserWithRole('Department User'); // assets.view only
        $manager->givePermissionTo('tags.view');
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();
        $assigner = $this->createUserWithRole('Asset Manager');
        app(TagService::class)->assignToAsset($tag, $asset, $assigner);

        $campaign = AuditCampaign::factory()->create(['status' => 'active']);
        AuditItem::factory()->create(['campaign_id' => $campaign->id, 'asset_id' => $asset->id]);

        $this->actingAs($manager)
            ->get(route('scan.resolve', $tag->fresh()->tag_number))
            ->assertRedirect(route('assets.show', $asset));
    }

    public function test_scanning_an_asset_with_no_open_item_goes_to_asset_detail(): void
    {
        $auditor = $this->createUserWithRole('Auditor');
        $auditor->givePermissionTo('tags.view');
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();
        app(TagService::class)->assignToAsset($tag, $asset, $auditor);

        $this->actingAs($auditor)
            ->get(route('scan.resolve', $tag->fresh()->tag_number))
            ->assertRedirect(route('assets.show', $asset));
    }
}
