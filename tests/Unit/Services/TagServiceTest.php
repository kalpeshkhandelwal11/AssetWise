<?php

namespace Tests\Unit\Services;

use App\Models\Asset;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TagServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TagService
    {
        return app(TagService::class);
    }

    // ------------------------------------------------------------- generateBatch

    public function test_generate_batch_produces_sequential_non_repeating_tag_numbers(): void
    {
        $actor = User::factory()->create();

        $batch = $this->service()->generateBatch(5, $actor);

        $this->assertSame(5, $batch->quantity);
        $this->assertCount(5, $batch->tags);

        $numbers = $batch->tags->pluck('tag_number')->sort()->values();
        $this->assertSame($numbers->unique()->count(), $numbers->count());

        foreach ($batch->tags as $tag) {
            $this->assertSame(str_pad((string) $tag->id, 6, '0', STR_PAD_LEFT), $tag->tag_number);
            $this->assertSame('available', $tag->status);
        }
    }

    public function test_generate_batch_uses_qr_setting_by_default(): void
    {
        $actor = User::factory()->create();

        $batch = $this->service()->generateBatch(2, $actor);

        $this->assertSame('qr', $batch->code_type);
        foreach ($batch->tags as $tag) {
            $this->assertSame('qr', $tag->code_type);
            $this->assertNotNull($tag->qr_payload);
            $this->assertNull($tag->barcode_value);
            $this->assertStringContainsString("/scan/{$tag->tag_number}", $tag->qr_payload);
        }
    }

    public function test_generate_batch_uses_barcode_when_setting_is_barcode(): void
    {
        Setting::set('tag_code_type', 'barcode');
        $actor = User::factory()->create();

        $batch = $this->service()->generateBatch(2, $actor);

        $this->assertSame('barcode', $batch->code_type);
        foreach ($batch->tags as $tag) {
            $this->assertSame('barcode', $tag->code_type);
            $this->assertNull($tag->qr_payload);
            $this->assertSame($tag->tag_number, $tag->barcode_value);
        }
    }

    public function test_changing_setting_mid_stream_does_not_relabel_already_generated_batches(): void
    {
        $actor = User::factory()->create();

        $firstBatch = $this->service()->generateBatch(2, $actor);
        $this->assertSame('qr', $firstBatch->code_type);

        Setting::set('tag_code_type', 'barcode');

        $secondBatch = $this->service()->generateBatch(2, $actor);
        $this->assertSame('barcode', $secondBatch->code_type);

        // First batch's tags are untouched by the later setting change.
        $firstBatch->refresh()->load('tags');
        $this->assertSame('qr', $firstBatch->code_type);
        foreach ($firstBatch->tags as $tag) {
            $this->assertSame('qr', $tag->fresh()->code_type);
        }
    }

    public function test_tag_numbers_are_global_and_independent_of_asset_category(): void
    {
        $actor = User::factory()->create();

        $batchOne = $this->service()->generateBatch(3, $actor);
        $batchTwo = $this->service()->generateBatch(3, $actor);

        $all = $batchOne->tags->pluck('tag_number')->merge($batchTwo->tags->pluck('tag_number'));
        $this->assertSame($all->unique()->count(), $all->count());
    }

    // ------------------------------------------------------------- assignToAsset

    public function test_assign_to_asset_activates_assignment_and_syncs_asset_tag(): void
    {
        $actor = User::factory()->create();
        $asset = Asset::factory()->create();
        $originalAssetId = $asset->asset_tag;
        $tag = Tag::factory()->available()->create();

        $assignment = $this->service()->assignToAsset($tag, $asset, $actor);

        $this->assertSame('active', $assignment->status);
        $this->assertSame('assigned', $tag->fresh()->status);
        // asset_tag (Asset ID) is untouched; the barcode link lives on the assignment.
        $this->assertSame($originalAssetId, $asset->fresh()->asset_tag);
        $this->assertSame($tag->id, $asset->fresh()->activeTag()->id);
    }

    public function test_assign_to_asset_rejects_a_second_active_assignment_on_the_same_asset(): void
    {
        $actor = User::factory()->create();
        $asset = Asset::factory()->create();
        $tagOne = Tag::factory()->available()->create();
        $tagTwo = Tag::factory()->available()->create();

        $this->service()->assignToAsset($tagOne, $asset, $actor);

        $this->expectException(ValidationException::class);

        $this->service()->assignToAsset($tagTwo, $asset, $actor);
    }

    public function test_assign_to_asset_rejects_a_non_available_tag(): void
    {
        $actor = User::factory()->create();
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->assigned()->create();

        $this->expectException(ValidationException::class);

        $this->service()->assignToAsset($tag, $asset, $actor);
    }

    // ------------------------------------------------------------- resolveScan

    public function test_resolve_scan_returns_available_for_a_pool_tag(): void
    {
        $tag = Tag::factory()->available()->create();

        $result = $this->service()->resolveScan($tag->tag_number);

        $this->assertSame('available', $result->status);
        $this->assertNull($result->asset);
    }

    public function test_resolve_scan_returns_assigned_with_the_owning_asset(): void
    {
        $actor = User::factory()->create();
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();
        $this->service()->assignToAsset($tag, $asset, $actor);

        $result = $this->service()->resolveScan($tag->fresh()->tag_number);

        $this->assertSame('assigned', $result->status);
        $this->assertSame($asset->id, $result->asset->id);
    }

    public function test_resolve_scan_returns_inactive_with_a_link_to_the_current_tag_after_replacement(): void
    {
        $actor = User::factory()->create();
        $asset = Asset::factory()->create();
        $oldTag = Tag::factory()->available()->create();
        $newTag = Tag::factory()->available()->create();

        $this->service()->assignToAsset($oldTag, $asset, $actor);
        $replacement = $this->service()->requestReplacement($asset, $newTag, 'Damaged label', $actor);
        $this->service()->applyReplacement($replacement);

        $result = $this->service()->resolveScan($oldTag->fresh()->tag_number);

        $this->assertSame('inactive', $result->status);
        $this->assertNotNull($result->currentTag);
        $this->assertSame($newTag->id, $result->currentTag->id);
    }

    // ------------------------------------------------------------- replacement (Phase 2)

    public function test_request_replacement_requires_an_existing_active_tag(): void
    {
        $actor = User::factory()->create();
        $asset = Asset::factory()->create();
        $newTag = Tag::factory()->available()->create();

        $this->expectException(ValidationException::class);

        $this->service()->requestReplacement($asset, $newTag, 'reason', $actor);
    }

    public function test_apply_replacement_deactivates_old_tag_and_activates_new_one(): void
    {
        $actor = User::factory()->create();
        $asset = Asset::factory()->create();
        $oldTag = Tag::factory()->available()->create();
        $newTag = Tag::factory()->available()->create();

        $this->service()->assignToAsset($oldTag, $asset, $actor);
        $replacement = $this->service()->requestReplacement($asset, $newTag, 'Damaged label', $actor);

        $this->service()->applyReplacement($replacement);

        $this->assertSame('inactive', $oldTag->fresh()->status);
        $this->assertSame('assigned', $newTag->fresh()->status);
        // asset_tag (Asset ID) is untouched by replacement; the new barcode is the active tag.
        $this->assertSame('approved', $replacement->fresh()->status);
        $this->assertSame($newTag->id, $asset->fresh()->activeTag()->id);
    }
}
