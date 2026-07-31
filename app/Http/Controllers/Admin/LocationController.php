<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Location;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    // ── Locations ───────────────────────────────────────────────────────────

    public function index(): View
    {
        $this->authorize('masters.manage');

        $locations = Location::with('buildings.floors.rooms')->orderBy('name')->get();

        return view('admin.locations.index', compact('locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('masters.manage');

        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'code'    => 'required|string|max:50|alpha_dash|unique:locations,code',
            'address' => 'nullable|string|max:500',
        ]);

        $data['code'] = strtoupper($data['code']);
        Location::create($data);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location created.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('masters.manage');

        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'code'    => 'required|string|max:50|alpha_dash|unique:locations,code,' . $location->id,
            'address' => 'nullable|string|max:500',
        ]);

        $data['code'] = strtoupper($data['code']);
        $location->update($data);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location updated.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $this->authorize('masters.manage');

        $location->delete(); // cascades to buildings/floors/rooms via FK

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location deleted.');
    }

    // ── Buildings ────────────────────────────────────────────────────────────

    public function storeBuilding(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('masters.manage');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', 'alpha_dash',
                       "unique:buildings,code,NULL,id,location_id,{$location->id}"],
        ]);

        $data['code'] = strtoupper($data['code']);
        $location->buildings()->create($data);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Building created.');
    }

    public function updateBuilding(Request $request, Building $building): RedirectResponse
    {
        $this->authorize('masters.manage');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', 'alpha_dash',
                       "unique:buildings,code,{$building->id},id,location_id,{$building->location_id}"],
        ]);

        $data['code'] = strtoupper($data['code']);
        $building->update($data);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Building updated.');
    }

    public function destroyBuilding(Building $building): RedirectResponse
    {
        $this->authorize('masters.manage');

        $building->delete();

        return redirect()->route('admin.locations.index')
            ->with('success', 'Building deleted.');
    }

    // ── Floors ───────────────────────────────────────────────────────────────

    public function storeFloor(Request $request, Building $building): RedirectResponse
    {
        $this->authorize('masters.manage');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', 'alpha_dash',
                       "unique:floors,code,NULL,id,building_id,{$building->id}"],
        ]);

        $data['code'] = strtoupper($data['code']);
        $building->floors()->create($data);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Floor created.');
    }

    public function updateFloor(Request $request, Floor $floor): RedirectResponse
    {
        $this->authorize('masters.manage');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', 'alpha_dash',
                       "unique:floors,code,{$floor->id},id,building_id,{$floor->building_id}"],
        ]);

        $data['code'] = strtoupper($data['code']);
        $floor->update($data);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Floor updated.');
    }

    public function destroyFloor(Floor $floor): RedirectResponse
    {
        $this->authorize('masters.manage');

        $floor->delete();

        return redirect()->route('admin.locations.index')
            ->with('success', 'Floor deleted.');
    }

    // ── Rooms ─────────────────────────────────────────────────────────────────

    public function storeRoom(Request $request, Floor $floor): RedirectResponse
    {
        $this->authorize('masters.manage');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', 'alpha_dash',
                       "unique:rooms,code,NULL,id,floor_id,{$floor->id}"],
        ]);

        $data['code'] = strtoupper($data['code']);
        $floor->rooms()->create($data);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Room created.');
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('masters.manage');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', 'alpha_dash',
                       "unique:rooms,code,{$room->id},id,floor_id,{$room->floor_id}"],
        ]);

        $data['code'] = strtoupper($data['code']);
        $room->update($data);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Room updated.');
    }

    public function destroyRoom(Room $room): RedirectResponse
    {
        $this->authorize('masters.manage');

        $room->delete();

        return redirect()->route('admin.locations.index')
            ->with('success', 'Room deleted.');
    }
}
