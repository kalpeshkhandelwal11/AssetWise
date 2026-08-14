<?php

namespace Tests\Feature\Approvals;

use App\Models\Asset;
use App\Models\Tag;
use App\Services\TagService;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * End-to-end: submit a tag replacement, approve through both levels of the seeded
 * tag_replacement workflow (Asset Manager -> Super Admin), and confirm
 * ApplyTagReplacement (the ApprovalRequestApproved listener) actually ran — old tag
 * scans inactive, new tag scans assigned. Deliberately does NOT fake
 * ApprovalRequestApproved: this test's entire point is proving the listener fires for
 * real, so faking that specific event would defeat it (CLAUDE.md's partial-fake guidance
 * is for isolating unrelated Eloquent-event side effects, not for suppressing the very
 * event under test).
 */
class TagReplacementApprovalTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function withWorkflow(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(WorkflowSeeder::class);
    }

    public function test_submit_requires_tags_replace_permission(): void
    {
        $this->withWorkflow();
        $asset = Asset::factory()->create();
        app(TagService::class)->assignToAsset(
            Tag::factory()->available()->create(),
            $asset,
            $this->createUserWithRole('Super Admin'),
        );
        $newTag = Tag::factory()->available()->create();
        $user = $this->createUserWithRole('Department User'); // no tags.replace

        $this->actingAs($user)
             ->post(route('assets.tags.replace.submit', $asset), [
                 'new_tag_id' => $newTag->id,
                 'reason'     => 'Damaged label',
             ])
             ->assertForbidden();
    }

    public function test_full_approval_chain_applies_the_replacement(): void
    {
        $this->withWorkflow();

        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Asset Manager');
        $levelTwoApprover = $this->createUserWithRole('Super Admin');

        $asset = Asset::factory()->create();
        $oldTag = Tag::factory()->available()->create();
        $newTag = Tag::factory()->available()->create();
        app(TagService::class)->assignToAsset($oldTag, $asset, $requester);

        $this->actingAs($requester)
             ->post(route('assets.tags.replace.submit', $asset), [
                 'new_tag_id' => $newTag->id,
                 'reason'     => 'Damaged label',
             ])
             ->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('tag_replacement_requests', [
            'asset_id'       => $asset->id,
            'current_tag_id' => $oldTag->id,
            'new_tag_id'     => $newTag->id,
            'status'         => 'pending',
        ]);

        $replacement = \App\Models\TagReplacementRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertNotNull($replacement->approval_request_id);

        // Level 1 — Asset Manager approves, request advances but nothing applied yet.
        $this->actingAs($levelOneApprover)
             ->post(route('approvals.approve', $replacement->approval_request_id))
             ->assertRedirect(route('approvals.index'));

        $this->assertSame('assigned', $oldTag->fresh()->status);
        $this->assertSame('pending', $replacement->fresh()->status);

        // Level 2 (final) — Super Admin approves, terminal step fires
        // ApprovalRequestApproved -> ApplyTagReplacement -> TagService::applyReplacement().
        $this->actingAs($levelTwoApprover)
             ->post(route('approvals.approve', $replacement->approval_request_id))
             ->assertRedirect(route('approvals.index'));

        $this->assertSame('inactive', $oldTag->fresh()->status);
        $this->assertSame('assigned', $newTag->fresh()->status);
        $this->assertSame('approved', $replacement->fresh()->status);
        // The new barcode becomes the active tag; the Asset ID (asset_tag) is left untouched.
        $this->assertSame($newTag->id, $asset->fresh()->activeTag()->id);

        // Scanning proves the routing outcome, not just the raw column state.
        $oldTagScan = app(TagService::class)->resolveScan($oldTag->fresh()->tag_number);
        $this->assertSame('inactive', $oldTagScan->status);
        $this->assertSame($newTag->id, $oldTagScan->currentTag->id);

        $newTagScan = app(TagService::class)->resolveScan($newTag->fresh()->tag_number);
        $this->assertSame('assigned', $newTagScan->status);
        $this->assertSame($asset->id, $newTagScan->asset->id);
    }

    public function test_rejection_leaves_the_original_tag_untouched(): void
    {
        $this->withWorkflow();

        $requester = $this->createUserWithRole('Asset Manager');
        $levelOneApprover = $this->createUserWithRole('Asset Manager');

        $asset = Asset::factory()->create();
        $oldTag = Tag::factory()->available()->create();
        $newTag = Tag::factory()->available()->create();
        app(TagService::class)->assignToAsset($oldTag, $asset, $requester);

        $this->actingAs($requester)
             ->post(route('assets.tags.replace.submit', $asset), [
                 'new_tag_id' => $newTag->id,
                 'reason'     => 'Damaged label',
             ]);

        $replacement = \App\Models\TagReplacementRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($levelOneApprover)
             ->post(route('approvals.reject', $replacement->approval_request_id), ['comment' => 'Not needed']);

        $this->assertSame('assigned', $oldTag->fresh()->status);
        $this->assertSame('available', $newTag->fresh()->status);
        // TagReplacementRequest.status is only flipped by applyReplacement() on approval;
        // a rejection resolves at the ApprovalRequest level (WorkflowService's own record).
        $this->assertSame('pending', $replacement->fresh()->status);
        $this->assertDatabaseHas('approval_requests', ['id' => $replacement->approval_request_id, 'status' => 'rejected']);
    }
}
