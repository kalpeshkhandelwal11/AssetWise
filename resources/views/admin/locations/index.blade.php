<x-app-layout>
    @section('page-title', 'Locations')

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.masters.landing') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Locations</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage the location hierarchy: Locations → Buildings → Floors → Rooms</p>
            </div>
        </div>

        <button x-data @click="$dispatch('open-location-modal')"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Location
        </button>
    </div>

    <div class="space-y-3">
        @forelse($locations as $location)
            <div x-data="{ expanded: false }"
                 class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">

                {{-- Location row --}}
                <div class="flex items-center gap-3 px-4 py-3">
                    <button @click="expanded = !expanded" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg :class="expanded ? 'rotate-90' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <div class="flex-1 min-w-0">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $location->name }}</span>
                        <span class="ml-2 font-mono text-xs text-gray-400">{{ $location->code }}</span>
                        @if($location->address)
                            <p class="text-xs text-gray-400 mt-0.5">{{ $location->address }}</p>
                        @endif
                    </div>
                    <span class="text-xs text-gray-400">{{ $location->buildings->count() }} building(s)</span>
                    <div class="flex items-center gap-1">
                        <button x-data @click="$dispatch('open-location-modal', {{ json_encode(['id' => $location->id, 'name' => $location->name, 'code' => $location->code, 'address' => $location->address]) }})"
                                class="p-1.5 text-gray-400 hover:text-indigo-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <button x-data @click="$dispatch('open-building-modal', {{ json_encode(['location_id' => $location->id, 'location_name' => $location->name]) }})"
                                class="p-1.5 text-gray-400 hover:text-green-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Add Building">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                        <form method="POST" action="{{ route('admin.locations.destroy', $location) }}"
                              onsubmit="return confirm('Delete this location and all its buildings, floors, and rooms?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Buildings --}}
                <div x-show="expanded" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                    @foreach($location->buildings as $building)
                        <div x-data="{ floorExpanded: false }" class="pl-8 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                            <div class="flex items-center gap-3 px-4 py-2.5 bg-gray-50/50 dark:bg-gray-900/30">
                                <button @click="floorExpanded = !floorExpanded" class="text-gray-400 hover:text-gray-600 transition-colors">
                                    <svg :class="floorExpanded ? 'rotate-90' : ''" class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $building->name }}</span>
                                    <span class="ml-2 font-mono text-xs text-gray-400">{{ $building->code }}</span>
                                </div>
                                <span class="text-xs text-gray-400">{{ $building->floors->count() }} floor(s)</span>
                                <div class="flex items-center gap-1">
                                    <button x-data @click="$dispatch('open-building-modal', {{ json_encode(['edit_id' => $building->id, 'location_id' => $location->id, 'location_name' => $location->name, 'name' => $building->name, 'code' => $building->code]) }})"
                                            class="p-1 text-gray-400 hover:text-indigo-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Edit">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button x-data @click="$dispatch('open-floor-modal', {{ json_encode(['building_id' => $building->id, 'building_name' => $building->name]) }})"
                                            class="p-1 text-gray-400 hover:text-green-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Add Floor">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                    <form method="POST" action="{{ route('admin.locations.buildings.destroy', $building) }}"
                                          onsubmit="return confirm('Delete building and all floors/rooms?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Floors --}}
                            <div x-show="floorExpanded" x-collapse>
                                @foreach($building->floors as $floor)
                                    <div x-data="{ roomExpanded: false }" class="pl-6 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                        <div class="flex items-center gap-3 px-4 py-2 bg-white/60 dark:bg-gray-800/60">
                                            <button @click="roomExpanded = !roomExpanded" class="text-gray-300 hover:text-gray-500 transition-colors">
                                                <svg :class="roomExpanded ? 'rotate-90' : ''" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </button>
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $floor->name }}</span>
                                                <span class="ml-2 font-mono text-xs text-gray-400">{{ $floor->code }}</span>
                                            </div>
                                            <span class="text-xs text-gray-400">{{ $floor->rooms->count() }} room(s)</span>
                                            <div class="flex items-center gap-1">
                                                <button x-data @click="$dispatch('open-floor-modal', {{ json_encode(['edit_id' => $floor->id, 'building_id' => $building->id, 'building_name' => $building->name, 'name' => $floor->name, 'code' => $floor->code]) }})"
                                                        class="p-1 text-gray-400 hover:text-indigo-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Edit">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                                <button x-data @click="$dispatch('open-room-modal', {{ json_encode(['floor_id' => $floor->id, 'floor_name' => $floor->name]) }})"
                                                        class="p-1 text-gray-400 hover:text-green-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Add Room">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                </button>
                                                <form method="POST" action="{{ route('admin.locations.floors.destroy', $floor) }}"
                                                      onsubmit="return confirm('Delete floor and all rooms?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="p-1 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        {{-- Rooms --}}
                                        <div x-show="roomExpanded" x-collapse class="pl-8">
                                            @forelse($floor->rooms as $room)
                                                <div class="flex items-center gap-3 px-4 py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0 bg-gray-50/30 dark:bg-gray-900/20">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300 flex-shrink-0"></span>
                                                    <div class="flex-1 text-xs text-gray-600 dark:text-gray-400">
                                                        {{ $room->name }}
                                                        <span class="ml-1 font-mono text-gray-400">{{ $room->code }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-1">
                                                        <button x-data @click="$dispatch('open-room-modal', {{ json_encode(['edit_id' => $room->id, 'floor_id' => $floor->id, 'floor_name' => $floor->name, 'name' => $room->name, 'code' => $room->code]) }})"
                                                                class="p-1 text-gray-400 hover:text-indigo-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Edit">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        </button>
                                                        <form method="POST" action="{{ route('admin.locations.rooms.destroy', $room) }}"
                                                              onsubmit="return confirm('Delete room?')">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="p-1 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="px-4 py-2 text-xs text-gray-400">No rooms yet.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                                @if($building->floors->isEmpty())
                                    <p class="pl-16 py-2 text-xs text-gray-400">No floors yet.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @if($location->buildings->isEmpty())
                        <p class="pl-10 py-3 text-sm text-gray-400">No buildings yet — add one using the + button.</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-16 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <p>No locations yet. Click <strong>Add Location</strong> to create the first one.</p>
            </div>
        @endforelse
    </div>

    {{-- Location Modal --}}
    @include('admin.locations.partials.location-modal')

    {{-- Building Modal --}}
    @include('admin.locations.partials.building-modal')

    {{-- Floor Modal --}}
    @include('admin.locations.partials.floor-modal')

    {{-- Room Modal --}}
    @include('admin.locations.partials.room-modal')
</x-app-layout>
