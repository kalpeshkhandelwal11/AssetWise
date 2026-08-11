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

        $response = $this->actingAs($auditor)->post(route('audits.items.verify', $item), [
            'status' => 'verified',
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
            'status' => 'damaged',
            'notes'  => 'Cracked screen',
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

        $this->actingAs($auditor)->post(route('audits.items.verify', $item), [
            'status' => 'verified',
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
}
