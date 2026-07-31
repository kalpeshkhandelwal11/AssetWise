<?php

namespace Tests\Feature\Admin;

use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Priority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MasterTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_landing_requires_auth(): void
    {
        $this->get(route('admin.masters.landing'))->assertRedirect('/login');
    }

    public function test_landing_lists_all_entity_cards(): void
    {
        $this->actingAs($this->admin())
             ->get(route('admin.masters.landing'))
             ->assertOk()
             ->assertSee('Asset Statuses')
             ->assertSee('Asset Types')
             ->assertSee('Priorities');
    }

    public function test_index_shows_records_for_entity(): void
    {
        AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true]);

        $this->actingAs($this->admin())
             ->get(route('admin.masters.index', 'statuses'))
             ->assertOk()
             ->assertSee('Available');
    }

    public function test_index_returns_404_for_unknown_entity(): void
    {
        $this->actingAs($this->admin())
             ->get(route('admin.masters.index', 'unknown-entity'))
             ->assertNotFound();
    }

    public function test_store_creates_asset_type(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.masters.store', 'asset-types'), [
                 'name' => 'Hardware',
                 'code' => 'HW',
             ])
             ->assertRedirect(route('admin.masters.index', 'asset-types'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('asset_types', ['code' => 'HW', 'name' => 'Hardware']);
    }

    public function test_store_requires_unique_code_per_entity(): void
    {
        AssetType::create(['name' => 'Existing', 'code' => 'DUP']);

        $this->actingAs($this->admin())
             ->post(route('admin.masters.store', 'asset-types'), ['name' => 'Another', 'code' => 'DUP'])
             ->assertSessionHasErrors('code');
    }

    public function test_update_modifies_record(): void
    {
        $item = Priority::create(['name' => 'Low', 'code' => 'LOW']);

        $this->actingAs($this->admin())
             ->put(route('admin.masters.update', ['priorities', $item->id]), [
                 'name' => 'Very Low',
                 'code' => 'VLOW',
             ])
             ->assertRedirect(route('admin.masters.index', 'priorities'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('priorities', ['id' => $item->id, 'name' => 'Very Low', 'code' => 'VLOW']);
    }

    public function test_toggle_active_flips_status(): void
    {
        $item = AssetType::create(['name' => 'Type A', 'code' => 'A', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->patch(route('admin.masters.toggle', ['asset-types', $item->id]));

        $this->assertFalse($item->refresh()->is_active);
    }

    public function test_system_status_cannot_be_deactivated(): void
    {
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AV', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);

        $this->actingAs($this->admin())
             ->patch(route('admin.masters.toggle', ['statuses', $status->id]))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertTrue($status->refresh()->is_active);
    }

    public function test_system_status_cannot_be_deleted(): void
    {
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AV', 'color' => '#22c55e', 'is_system' => true]);

        $this->actingAs($this->admin())
             ->delete(route('admin.masters.destroy', ['statuses', $status->id]))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertDatabaseHas('asset_statuses', ['id' => $status->id]);
    }

    public function test_non_system_record_can_be_deleted(): void
    {
        $item = AssetType::create(['name' => 'Old Type', 'code' => 'OLD']);

        $this->actingAs($this->admin())
             ->delete(route('admin.masters.destroy', ['asset-types', $item->id]))
             ->assertRedirect()
             ->assertSessionHas('success');

        $this->assertDatabaseMissing('asset_types', ['id' => $item->id]);
    }

    public function test_viewer_cannot_manage_masters(): void
    {
        $viewer = $this->createUserWithRole('Viewer');

        $this->actingAs($viewer)
             ->get(route('admin.masters.landing'))
             ->assertForbidden();
    }
}
