<?php

namespace Tests\Feature\Pwa;

use App\Models\Asset;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ScanPageTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_scan_page_renders_for_a_tags_view_holder(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)
            ->get(route('scan.index'))
            ->assertOk()
            ->assertSee('Scan a Tag')
            ->assertSee('Start camera')
            // The manual fallback must be present regardless of camera support (UF-18 step 4).
            ->assertSee('Or enter the tag number');
    }

    public function test_scan_page_is_denied_without_tags_view(): void
    {
        $deptUser = $this->createUserWithRole('Department User');

        $this->actingAs($deptUser)
            ->get(route('scan.index'))
            ->assertForbidden();
    }

    public function test_scan_page_requires_authentication(): void
    {
        $this->get(route('scan.index'))->assertRedirect('/login');
    }

    /** The new GET /scan must not shadow the M05 resolver it sits in front of. */
    public function test_scan_index_does_not_shadow_the_tag_resolver(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();
        app(TagService::class)->assignToAsset($tag, $asset, $manager);

        $this->actingAs($manager)
            ->get(route('scan.resolve', $tag->fresh()->tag_number))
            ->assertRedirect(route('assets.show', $asset));
    }

    public function test_manual_entry_resolves_through_to_the_scan_resolver(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();
        app(TagService::class)->assignToAsset($tag, $asset, $manager);
        $tagNumber = $tag->fresh()->tag_number;

        $this->actingAs($manager)
            ->post(route('scan.lookup'), ['tag_number' => $tagNumber])
            ->assertRedirect(route('scan.resolve', $tagNumber));
    }

    public function test_manual_entry_trims_whitespace_around_a_typed_tag(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $tag = Tag::factory()->available()->create();
        app(TagService::class)->assignToAsset($tag, $asset, $manager);
        $tagNumber = $tag->fresh()->tag_number;

        $this->actingAs($manager)
            ->post(route('scan.lookup'), ['tag_number' => "  {$tagNumber}  "])
            ->assertRedirect(route('scan.resolve', $tagNumber));
    }

    public function test_manual_entry_requires_a_tag_number(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)
            ->post(route('scan.lookup'), ['tag_number' => ''])
            ->assertSessionHasErrors('tag_number');
    }

    public function test_manual_entry_is_denied_without_tags_view(): void
    {
        $deptUser = $this->createUserWithRole('Department User');

        $this->actingAs($deptUser)
            ->post(route('scan.lookup'), ['tag_number' => 'AW-000001'])
            ->assertForbidden();
    }

    /**
     * M10's audit-scan branch gates on tags.view, so the seeded Auditor role needs it or an
     * auditor can never reach a campaign item by scanning — the field workflow M15 exists for.
     */
    public function test_seeded_auditor_can_scan(): void
    {
        $auditor = $this->createUserWithRole('Auditor');

        $this->assertTrue($auditor->can('tags.view'));

        $this->actingAs($auditor)
            ->get(route('scan.index'))
            ->assertOk();
    }
}
