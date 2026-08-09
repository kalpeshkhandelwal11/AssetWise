<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ScanControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_scan_requires_auth(): void
    {
        $tag = Tag::factory()->available()->create();

        $this->get(route('scan.resolve', $tag->tag_number))->assertRedirect('/login');
    }

    public function test_scan_requires_tags_view_permission(): void
    {
        $tag = Tag::factory()->available()->create();
        $user = $this->createUserWithRole('Department User'); // no tags.view

        $this->actingAs($user)
             ->get(route('scan.resolve', $tag->tag_number))
             ->assertForbidden();
    }

    public function test_scan_an_available_tag_shows_the_assign_prompt(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $tag = Tag::factory()->available()->create();

        $this->actingAs($manager)
             ->get(route('scan.resolve', $tag->tag_number))
             ->assertOk()
             ->assertSee('available');

        $this->assertDatabaseHas('scan_logs', ['tag_id' => $tag->id, 'user_id' => $manager->id, 'method' => 'manual']);
    }

    public function test_scan_an_assigned_tag_redirects_to_the_asset(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();
        app(TagService::class)->assignToAsset($tag, $asset, $manager);

        $this->actingAs($manager)
             ->get(route('scan.resolve', $tag->fresh()->tag_number))
             ->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('scan_logs', ['tag_id' => $tag->id, 'asset_id' => $asset->id]);
    }

    public function test_scan_an_inactive_tag_shows_the_retired_message(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $service = app(TagService::class);
        $oldTag = Tag::factory()->available()->create();
        $newTag = Tag::factory()->available()->create();
        $service->assignToAsset($oldTag, $asset, $manager);
        $replacement = $service->requestReplacement($asset, $newTag, 'Damaged', $manager);
        $service->applyReplacement($replacement);

        $this->actingAs($manager)
             ->get(route('scan.resolve', $oldTag->fresh()->tag_number))
             ->assertOk()
             ->assertSee('retired')
             ->assertSee($newTag->tag_number);
    }

    public function test_api_scan_log_writes_a_scan_log_and_returns_json(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $tag = Tag::factory()->available()->create();

        $this->actingAs($manager)
             ->postJson('/api/scan', ['tag_number' => $tag->tag_number, 'method' => 'camera'])
             ->assertOk()
             ->assertJson(['status' => 'available', 'tag_number' => $tag->tag_number]);

        $this->assertDatabaseHas('scan_logs', ['tag_id' => $tag->id, 'method' => 'camera']);
    }
}
