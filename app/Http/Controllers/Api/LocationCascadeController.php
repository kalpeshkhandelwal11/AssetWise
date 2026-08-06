<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationCascadeController extends Controller
{
    public function buildings(Request $request): JsonResponse
    {
        $request->validate(['location_id' => 'required|exists:locations,id']);

        return response()->json(
            Building::where('location_id', $request->location_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function floors(Request $request): JsonResponse
    {
        $request->validate(['building_id' => 'required|exists:buildings,id']);

        return response()->json(
            Floor::where('building_id', $request->building_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function rooms(Request $request): JsonResponse
    {
        $request->validate(['floor_id' => 'required|exists:floors,id']);

        return response()->json(
            Room::where('floor_id', $request->floor_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
        );
    }
}
