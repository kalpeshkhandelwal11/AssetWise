<?php

namespace Tests\Feature\Kits;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Kit;
use App\Models\KitItem;
use App\Services\KitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class KitTemplateTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_creating_a_kit_requires_kits_manage(): void
    {
        $viewer = $this->createUserWithRole('Viewer');

        $this->actingAs($viewer)->post(route('kits.store'), [
            'name' => 'Dev Workstation',
            'code' => 'DEV-WS',
        ])->assertForbidden();
    }

    public function test_manager_can_create_kit_add_slot_and_link_asset(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $category = AssetCategory::factory()->create();
        $asset = Asset::factory()->create(['category_id' => $category->id]);

        $this->actingAs($manager)->post(route('kits.store'), [
            'name' => 'Dev Workstation',
            'code' => 'DEV-WS',
        ])->assertRedirect();

        $kit = Kit::firstOrFail();

        $this->actingAs($manager)->post(route('kits.items.store', $kit), [
            'label'       => 'Laptop',
            'category_id' => $category->id,
            'quantity'    => 1,
        ])->assertRedirect();

        $item = KitItem::firstOrFail();

        $this->actingAs($manager)->post(route('kits.items.assets.store', [$kit, $item]), [
            'asset_id' => $asset->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('kit_assets', ['kit_item_id' => $item->id, 'asset_id' => $asset->id]);
        $this->assertTrue(app(KitService::class)->isReady($kit->fresh()));
    }

    public function test_linking_an_asset_of_the_wrong_category_is_rejected(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $wanted = AssetCategory::factory()->create();
        $other = AssetCategory::factory()->create();
        $asset = Asset::factory()->create(['category_id' => $other->id]);

        $kit = Kit::create(['name' => 'K', 'code' => 'K1', 'is_active' => true, 'created_by' => $manager->id]);
        $item = $kit->items()->create(['label' => 'Laptop', 'category_id' => $wanted->id, 'quantity' => 1]);

        $this->actingAs($manager)
            ->post(route('kits.items.assets.store', [$kit, $item]), ['asset_id' => $asset->id])
            ->assertSessionHasErrors('asset_id');

        $this->assertDatabaseCount('kit_assets', 0);
    }

    public function test_asset_detail_lists_kit_memberships(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();

        $kit = Kit::create(['name' => 'Dev Workstation', 'code' => 'DEV-WS', 'is_active' => true, 'created_by' => $manager->id]);
        $item = $kit->items()->create(['label' => 'Laptop', 'quantity' => 1]);
        $item->kitAssets()->create(['asset_id' => $asset->id]);

        $this->actingAs($manager)
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Dev Workstation');
    }

    public function test_readiness_is_false_until_every_slot_is_filled(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $service = app(KitService::class);

        $kit = Kit::create(['name' => 'K', 'code' => 'K1', 'is_active' => true, 'created_by' => $manager->id]);
        $item = $kit->items()->create(['label' => 'Laptop', 'quantity' => 2]);

        $this->assertFalse($service->isReady($kit->fresh()));

        $item->kitAssets()->create(['asset_id' => Asset::factory()->create()->id]);
        $this->assertFalse($service->isReady($kit->fresh())); // 1 of 2

        $item->kitAssets()->create(['asset_id' => Asset::factory()->create()->id]);
        $this->assertTrue($service->isReady($kit->fresh())); // 2 of 2
    }
}
