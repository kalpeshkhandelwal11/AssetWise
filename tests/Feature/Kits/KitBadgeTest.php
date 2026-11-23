<?php

namespace Tests\Feature\Kits;

use App\Models\Asset;
use App\Models\Kit;
use App\Models\KitAsset;
use App\Models\KitItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/** An asset linked into a kit shows a "Kit" tag on the asset list and its detail page. */
class KitBadgeTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_kit_members_are_tagged_on_the_list_and_detail_page(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('Super Admin');
        $inKit = Asset::factory()->create(['name' => 'Kit Laptop']);
        $loose = Asset::factory()->create(['name' => 'Loose Laptop']);

        $kit = Kit::create(['name' => 'Engineer Kit', 'code' => 'KIT-ENG', 'is_active' => true, 'created_by' => $admin->id]);
        $item = KitItem::create(['kit_id' => $kit->id, 'label' => 'Laptop', 'category_id' => $inKit->category_id, 'quantity' => 1, 'sort_order' => 1]);
        KitAsset::create(['kit_item_id' => $item->id, 'asset_id' => $inKit->id]);

        $list = $this->actingAs($admin)->get(route('assets.index'))->assertOk();
        $this->assertSame(1, substr_count($list->getContent(), 'Kit: KIT-ENG'));
        $list->assertSee(route('kits.edit', $kit));

        $this->actingAs($admin)->get(route('assets.show', $inKit))->assertOk()->assertSee('Kit: KIT-ENG');
        $this->actingAs($admin)->get(route('assets.show', $loose))->assertOk()->assertDontSee('Kit: ');
    }

    public function test_assets_out_in_an_ad_hoc_bundle_are_tagged_until_returned(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create(['name' => 'Bundle Phone']);
        $type = \App\Models\MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true]);

        $out = \App\Models\KitAssignment::create(['kit_id' => null, 'direction' => 'out', 'movement_type_id' => $type->id,
            'approval_mode' => 'single', 'status' => 'completed', 'requested_by' => $admin->id]);
        \App\Models\AssetMovement::factory()->create(['asset_id' => $asset->id, 'kit_assignment_id' => $out->id]);

        $this->actingAs($admin)->get(route('assets.index'))->assertOk()->assertSee('Bundle #' . $out->id);
        $this->actingAs($admin)->get(route('assets.show', $asset))->assertOk()->assertSee('Bundle #' . $out->id);

        \App\Models\KitAssignment::create(['kit_id' => null, 'direction' => 'return', 'parent_assignment_id' => $out->id,
            'movement_type_id' => $type->id, 'approval_mode' => 'single', 'status' => 'completed', 'requested_by' => $admin->id]);

        $this->actingAs($admin)->get(route('assets.show', $asset))->assertOk()->assertDontSee('Bundle #' . $out->id);
    }
}
