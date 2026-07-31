<?php

namespace Tests\Feature\Admin;

use App\Models\Building;
use App\Models\Floor;
use App\Models\Location;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.locations.index'))->assertRedirect('/login');
    }

    public function test_index_shows_locations(): void
    {
        Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->get(route('admin.locations.index'))
             ->assertOk()
             ->assertSee('HQ');
    }

    public function test_store_location_creates_record(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.locations.store'), [
                 'name' => 'Branch Office',
                 'code' => 'branch',  // lowercase, should be uppercased
             ])
             ->assertRedirect(route('admin.locations.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('locations', ['code' => 'BRANCH', 'name' => 'Branch Office']);
    }

    public function test_store_location_requires_unique_code(): void
    {
        Location::create(['name' => 'Existing', 'code' => 'EXIST', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->post(route('admin.locations.store'), ['name' => 'Dup', 'code' => 'EXIST'])
             ->assertSessionHasErrors('code');
    }

    public function test_update_location_modifies_record(): void
    {
        $location = Location::create(['name' => 'Old', 'code' => 'OLD', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->put(route('admin.locations.update', $location), ['name' => 'New HQ', 'code' => 'HQ2'])
             ->assertRedirect(route('admin.locations.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'New HQ']);
    }

    public function test_destroy_location_deletes_cascade(): void
    {
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);
        $building = Building::create(['location_id' => $location->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->delete(route('admin.locations.destroy', $location))
             ->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
        $this->assertDatabaseMissing('buildings', ['id' => $building->id]);
    }

    public function test_store_building_creates_under_location(): void
    {
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->post(route('admin.locations.buildings.store', $location), [
                 'name' => 'Tower A',
                 'code' => 'TOWER_A',
             ])
             ->assertRedirect(route('admin.locations.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('buildings', ['location_id' => $location->id, 'code' => 'TOWER_A']);
    }

    public function test_store_floor_creates_under_building(): void
    {
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);
        $building = Building::create(['location_id' => $location->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->post(route('admin.locations.buildings.floors.store', $building), [
                 'name' => 'Ground Floor',
                 'code' => 'GF',
             ])
             ->assertRedirect(route('admin.locations.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('floors', ['building_id' => $building->id, 'code' => 'GF']);
    }

    public function test_store_room_creates_under_floor(): void
    {
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);
        $building = Building::create(['location_id' => $location->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $floor    = Floor::create(['building_id' => $building->id, 'name' => 'GF', 'code' => 'GF', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->post(route('admin.locations.floors.rooms.store', $floor), [
                 'name' => 'Server Room',
                 'code' => 'SVR',
             ])
             ->assertRedirect(route('admin.locations.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('rooms', ['floor_id' => $floor->id, 'code' => 'SVR']);
    }

    public function test_destroy_room(): void
    {
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);
        $building = Building::create(['location_id' => $location->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $floor    = Floor::create(['building_id' => $building->id, 'name' => 'GF', 'code' => 'GF', 'is_active' => true]);
        $room     = Room::create(['floor_id' => $floor->id, 'name' => 'Lab', 'code' => 'LAB', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->delete(route('admin.locations.rooms.destroy', $room))
             ->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }
}
