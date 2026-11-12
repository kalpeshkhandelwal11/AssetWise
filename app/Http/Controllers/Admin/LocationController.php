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

        $locations = Location::with('buildings.floors.rooms', 'manager')->orderBy('name')->get();
        $managers = \App\Models\Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_code', 'user_id']);

        return view('admin.locations.index', compact('locations', 'managers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('masters.manage');

        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'code'    => 'required|string|max:50|alpha_dash|unique:locations,code',
            'address' => 'nullable|string|max:500',
            'manager_employee_id' => 'nullable|exists:employees,id',
            'chainage'            => 'nullable|string|max:50',
        ]);

        $data['is_toll_plaza'] = $request->boolean('is_toll_plaza');

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
            'manager_employee_id' => 'nullable|exists:employees,id',
            'chainage'            => 'nullable|string|max:50',
        ]);

        $data['is_toll_plaza'] = $request->boolean('is_toll_plaza');

        $data['code'] = strtoupper($data['code']);
        $location->update($data);

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location updated.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $this->authorize('masters.manage');

        if ($reason = $this->inUse('location_id', $location->id, movements: true)) {
            return back()->with('error', "Cannot delete this location: {$reason}. Deactivate it instead.");
        }

        $location->delete(); // cascades to buildings/floors/rooms via FK

        return redirect()->route('admin.locations.index')
            ->with('success', 'Location deleted.');
    }

    /**
     * Assets reference these with nullOnDelete, so a delete would silently blank the place on
     * every asset there (and on movement history). Counts every company's assets, deleted ones
     * included, since those can be restored.
     */
    private function inUse(string $column, int $id, bool $movements = false): ?string
    {
        $assets = \Illuminate\Support\Facades\DB::table('assets')->where($column, $id)->count();
        if ($assets > 0) {
            return "{$assets} asset(s) are recorded here";
        }

        if ($movements) {
            $moves = \Illuminate\Support\Facades\DB::table('asset_movements')
                ->where(fn ($q) => $q->where('from_location_id', $id)->orWhere('to_location_id', $id))->count();
            if ($moves > 0) {
                return "{$moves} movement record(s) refer to it";
            }
        }

        return null;
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

        if ($reason = $this->inUse('building_id', $building->id)) {
            return back()->with('error', "Cannot delete this building: {$reason}.");
        }

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

        if ($reason = $this->inUse('floor_id', $floor->id)) {
            return back()->with('error', "Cannot delete this floor: {$reason}.");
        }

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

        if ($reason = $this->inUse('room_id', $room->id)) {
            return back()->with('error', "Cannot delete this room: {$reason}.");
        }

        $room->delete();

        return redirect()->route('admin.locations.index')
            ->with('success', 'Room deleted.');
    }
}
