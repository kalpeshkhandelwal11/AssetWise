<?php

namespace Tests\Feature\Api;

use App\Models\Building;
use App\Models\Floor;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationCascadeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buildings_endpoint_requires_auth(): void
    {
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);

        $this->getJson("/api/buildings?location_id={$location->id}")->assertUnauthorized();
    }

    public function test_buildings_endpoint_returns_buildings_for_location(): void
    {
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);
        $building = Building::create(['location_id' => $location->id, 'name' => 'Main Tower', 'code' => 'MAIN', 'is_active' => true]);
        $otherLocation = Location::create(['name' => 'Branch', 'code' => 'BR', 'is_active' => true]);
        Building::create(['location_id' => $otherLocation->id, 'name' => 'Other Building', 'code' => 'OTHER', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
             ->getJson("/api/buildings?location_id={$location->id}")
             ->assertOk()
             ->assertJsonCount(1)
             ->assertJsonFragment(['name' => 'Main Tower']);
    }

    public function test_floors_endpoint_returns_floors_for_building(): void
    {
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);
        $building = Building::create(['location_id' => $location->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $floor = Floor::create(['building_id' => $building->id, 'name' => 'Ground Floor', 'code' => 'GF', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
             ->getJson("/api/floors?building_id={$building->id}")
             ->assertOk()
             ->assertJsonFragment(['name' => 'Ground Floor']);
    }

    public function test_rooms_endpoint_returns_rooms_for_floor(): void
    {
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);
        $building = Building::create(['location_id' => $location->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $floor = Floor::create(['building_id' => $building->id, 'name' => 'GF', 'code' => 'GF', 'is_active' => true]);
        $room = Room::create(['floor_id' => $floor->id, 'name' => 'Server Room', 'code' => 'SVR', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
             ->getJson("/api/rooms?floor_id={$floor->id}")
             ->assertOk()
             ->assertJsonFragment(['name' => 'Server Room']);
    }

    public function test_buildings_endpoint_validates_location_id(): void
    {
        $this->actingAs(User::factory()->create())
             ->getJson('/api/buildings?location_id=999999')
             ->assertJsonValidationErrors('location_id');
    }
}
