<?php

namespace Tests\Feature\Audit;

use App\Models\AuditCampaign;
use App\Models\AuditItem;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AuditVerificationTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_auditor_can_mark_item_verified(): void
    {
        $auditor = $this->createUserWithRole('Auditor');
        $campaign = AuditCampaign::factory()->create(['status' => 'active']);
        $campaign->auditors()->sync([$auditor->id]);
        $item = AuditItem::factory()->create(['campaign_id' => $campaign->id]);

        Storage::fake('public');
        $response = $this->actingAs($auditor)->post(route('audits.items.verify', $item), [
            'status'     => 'verified',
            'tag_number' => $this->assetIdOf($item),
            'photo'      => UploadedFile::fake()->image('asset.jpg'),
        ]);

        $response->assertRedirect(route('audits.verify', ['campaign' => $campaign->id]));
        $item->refresh();
        $this->assertSame('verified', $item->status);
        $this->assertSame($auditor->id, $item->verified_by);
        $this->assertNotNull($item->verified_at);
    }

    public function test_missing_requires_notes(): void
    {
        $auditor = $this->createUserWithRole('Auditor');
        $campaign = AuditCampaign::factory()->create(['status' => 'active']);
        $campaign->auditors()->sync([$auditor->id]);
        $item = AuditItem::factory()->create(['campaign_id' => $campaign->id]);

        $this->actingAs($auditor)->post(route('audits.items.verify', $item), [
            'status' => 'missing',
        ])->assertSessionHasErrors('notes');

        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_damaged_with_notes_and_photo_is_recorded(): void
    {
        Storage::fake('public');
        $auditor = $this->createUserWithRole('Auditor');
        $campaign = AuditCampaign::factory()->create(['status' => 'active']);
        $campaign->auditors()->sync([$auditor->id]);
        $item = AuditItem::factory()->create(['campaign_id' => $campaign->id]);

        $this->actingAs($auditor)->post(route('audits.items.verify', $item), [
            'status'     => 'damaged',
            'tag_number' => $this->assetIdOf($item),
            'notes'      => 'Cracked screen',
            'photo'  => UploadedFile::fake()->image('damage.jpg'),
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('damaged', $item->status);
        $this->assertSame('Cracked screen', $item->notes);
        Storage::disk('public')->assertExists($item->photo_path);
    }

    public function test_unassigned_auditor_cannot_verify(): void
    {
        // A bare 'audit.verify' holder — not the seeded Auditor role, which also carries
        // 'audit.manage' and would bypass the per-campaign assignment guard being tested here.
        $this->seedRolesAndPermissions();
        $auditor = User::factory()->create();
        $auditor->givePermissionTo('audit.verify');
        $campaign = AuditCampaign::factory()->create(['status' => 'active']);
        $item = AuditItem::factory()->create(['campaign_id' => $campaign->id]);

        Storage::fake('public');
        $this->actingAs($auditor)->post(route('audits.items.verify', $item), [
            'status'     => 'verified',
            'tag_number' => $this->assetIdOf($item),
            'photo'      => UploadedFile::fake()->image('asset.jpg'),
        ])->assertSessionHasErrors('auditor');

        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_cannot_verify_after_campaign_closed(): void
    {
        $auditor = $this->createUserWithRole('Auditor');
        $campaign = AuditCampaign::factory()->create(['status' => 'closed', 'closed_at' => now()]);
        $campaign->auditors()->sync([$auditor->id]);
        $item = AuditItem::factory()->create(['campaign_id' => $campaign->id]);

        $this->expectException(ValidationException::class);
        app(AuditService::class)->verify($item, ['status' => 'verified'], $auditor);
    }

    public function test_cannot_verify_a_draft_campaigns_item(): void
    {
        $auditor = $this->createUserWithRole('Auditor');
        $campaign = AuditCampaign::factory()->create(['status' => 'draft']);
        $campaign->auditors()->sync([$auditor->id]);
        $item = AuditItem::factory()->create(['campaign_id' => $campaign->id]);

        $this->expectException(ValidationException::class);
        app(AuditService::class)->verify($item, ['status' => 'verified'], $auditor);
    }

    public function test_progress_counts_reflect_item_statuses(): void
    {
        $auditor = $this->createUserWithRole('Auditor');
        $campaign = AuditCampaign::factory()->create(['status' => 'active']);
        AuditItem::factory()->create(['campaign_id' => $campaign->id, 'status' => 'pending']);
        AuditItem::factory()->create(['campaign_id' => $campaign->id, 'status' => 'verified']);
        AuditItem::factory()->create(['campaign_id' => $campaign->id, 'status' => 'missing']);
        AuditItem::factory()->create(['campaign_id' => $campaign->id, 'status' => 'damaged']);

        $progress = app(AuditService::class)->progressFor($campaign);

        $this->assertSame(4, $progress['total']);
        $this->assertSame(1, $progress['pending']);
        $this->assertSame(1, $progress['verified']);
        $this->assertSame(1, $progress['missing']);
        $this->assertSame(1, $progress['damaged']);
        $this->assertSame(75.0, $progress['verified_pct']);
    }

    public function test_verify_worklist_renders_for_an_assigned_auditor(): void
    {
        $auditor = $this->createUserWithRole('Auditor');
        $campaign = AuditCampaign::factory()->create(['status' => 'active', 'name' => 'Worklist Campaign']);
        $campaign->auditors()->sync([$auditor->id]);
        $item = AuditItem::factory()->create(['campaign_id' => $campaign->id]);

        $this->actingAs($auditor)
            ->get(route('audits.verify', ['campaign' => $campaign->id, 'item' => $item->id]))
            ->assertOk()
            ->assertSee('Worklist Campaign')
            ->assertSee('Submit Verification');
    }

    public function test_verify_worklist_shows_empty_state_with_no_assigned_campaigns(): void
    {
        $auditor = $this->createUserWithRole('Auditor');

        $this->actingAs($auditor)->get(route('audits.verify'))
            ->assertOk()
            ->assertSee('not assigned to any active audit campaign');
    }

    private function activeItemFor(User $auditor, ?\App\Models\Asset $asset = null): AuditItem
    {
        $campaign = AuditCampaign::factory()->create(['status' => 'active']);
        $campaign->auditors()->sync([$auditor->id]);

        return AuditItem::factory()->create(['campaign_id' => $campaign->id] + ($asset ? ['asset_id' => $asset->id] : []));
    }

    public function test_verified_or_damaged_needs_a_photo_and_a_scan(): void
    {
        Storage::fake('public');
        $auditor = $this->createUserWithRole('Auditor');
        $item = $this->activeItemFor($auditor);

        $this->actingAs($auditor)->post(route('audits.items.verify', $item), ['status' => 'verified'])
            ->assertSessionHasErrors(['photo', 'tag_number'])
            ->assertSessionHas('audit_item_error', $item->id);

        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_damaged_needs_a_note_and_the_error_is_shown_on_that_item(): void
    {
        Storage::fake('public');
        $auditor = $this->createUserWithRole('Auditor');
        $item = $this->activeItemFor($auditor);

        // Submit and land back on the checklist, as the browser does: the message is shown
        // there (the old page swallowed it, so the submit looked like it did nothing).
        $this->actingAs($auditor)
            ->from(route('audits.verify', ['campaign' => $item->campaign_id]))
            ->followingRedirects()
            ->post(route('audits.items.verify', $item), [
                'status' => 'damaged', 'tag_number' => $this->assetIdOf($item), 'photo' => UploadedFile::fake()->image('a.jpg'),
            ])
            ->assertOk()
            ->assertSee('A note is required when marking an asset missing or damaged.');
        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_missing_needs_only_a_note(): void
    {
        $auditor = $this->createUserWithRole('Auditor');
        $item = $this->activeItemFor($auditor);

        $this->actingAs($auditor)->post(route('audits.items.verify', $item), [
            'status' => 'missing', 'notes' => 'Not in the server room',
        ])->assertSessionHasNoErrors();

        $this->assertSame('missing', $item->fresh()->status);
    }

    public function test_a_tagged_asset_is_confirmed_only_by_its_tag_not_its_asset_id(): void
    {
        Storage::fake('public');
        $auditor = $this->createUserWithRole('Auditor');
        $asset = \App\Models\Asset::factory()->create();
        $tag = \App\Models\Tag::factory()->available()->create();
        app(\App\Services\TagService::class)->assignToAsset($tag, $asset, $auditor);
        $item = $this->activeItemFor($auditor, $asset);

        $this->actingAs($auditor)->post(route('audits.items.verify', $item), [
            'status' => 'verified', 'tag_number' => $asset->asset_tag, 'photo' => UploadedFile::fake()->image('a.jpg'),
        ])->assertSessionHasErrors('tag_number');
        $this->assertSame('pending', $item->fresh()->status);

        $this->actingAs($auditor)->post(route('audits.items.verify', $item), [
            'status' => 'verified', 'tag_number' => $tag->fresh()->tag_number, 'photo' => UploadedFile::fake()->image('a.jpg'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('verified', $item->fresh()->status);
        Storage::disk('public')->assertExists($item->fresh()->photo_path);
    }

    /** Factory assets have no Asset ID (the naming series runs in AssetService); give one. */
    private function assetIdOf(AuditItem $item): string
    {
        if (blank($item->asset->asset_tag)) {
            $item->asset->update(['asset_tag' => 'AST-T' . $item->asset_id]);
        }

        return $item->asset->asset_tag;
    }
}
