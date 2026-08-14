<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class TagAssignmentTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_assign_requires_tags_assign_permission(): void
    {
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();
        $user = $this->createUserWithRole('Viewer'); // no tags.assign

        $this->actingAs($user)
             ->post(route('assets.tags.assign', $asset), ['tag_id' => $tag->id])
             ->assertForbidden();
    }

    public function test_assign_by_tag_id_syncs_asset_tag_and_shows_on_the_asset_page(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $originalAssetId = $asset->asset_tag;
        $tag = Tag::factory()->available()->create();

        $this->actingAs($manager)
             ->post(route('assets.tags.assign', $asset), ['tag_id' => $tag->id])
             ->assertRedirect(route('assets.show', $asset));

        // asset_tag (the generated Asset ID) is preserved; the barcode links via the assignment.
        $this->assertSame($originalAssetId, $asset->fresh()->asset_tag);
        $this->assertSame($tag->id, $asset->fresh()->activeTag()->id);
        $this->assertDatabaseHas('asset_tag_assignments', [
            'asset_id' => $asset->id,
            'tag_id'   => $tag->id,
            'status'   => 'active',
        ]);

        $this->actingAs($manager)
             ->get(route('assets.show', $asset))
             ->assertOk()
             ->assertSee($tag->tag_number);
    }

    public function test_assign_by_scanned_tag_number(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();

        $this->actingAs($manager)
             ->post(route('assets.tags.assign', $asset), ['tag_number' => $tag->tag_number])
             ->assertRedirect(route('assets.show', $asset));

        $this->assertSame('assigned', $tag->fresh()->status);
    }

    public function test_assign_fails_when_asset_already_has_an_active_tag(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        app(TagService::class)->assignToAsset(Tag::factory()->available()->create(), $asset, $manager);
        $secondTag = Tag::factory()->available()->create();

        $this->actingAs($manager)
             ->post(route('assets.tags.assign', $asset), ['tag_id' => $secondTag->id])
             ->assertSessionHasErrors('asset');
    }

    public function test_tag_history_lists_all_assignments_on_the_asset_page(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $oldTag = Tag::factory()->available()->create();
        $newTag = Tag::factory()->available()->create();
        $service = app(TagService::class);

        $service->assignToAsset($oldTag, $asset, $manager);
        $replacement = $service->requestReplacement($asset, $newTag, 'Damaged', $manager);
        $service->applyReplacement($replacement);

        $this->actingAs($manager)
             ->get(route('assets.show', $asset))
             ->assertOk()
             ->assertSee($oldTag->tag_number)
             ->assertSee($newTag->tag_number);
    }
}
