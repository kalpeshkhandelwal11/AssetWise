@php
    $canChangeCompany = ! $asset->exists || auth()->user()->can('companies.manage');
@endphp
<x-app-layout>
    @section('page-title', $asset->exists ? 'Edit Asset' : 'New Asset')

    <div class="max-w-4xl"
         x-data="{
            locationId: '{{ old('location_id', $asset->location_id) }}',
            buildingId: '{{ old('building_id', $asset->building_id) }}',
            floorId: '{{ old('floor_id', $asset->floor_id) }}',
            roomId: '{{ old('room_id', $asset->room_id) }}',
            buildings: [], floors: [], rooms: [],
            categoryId: '{{ old('category_id', $asset->category_id) }}',
            dynamicFields: {{ Illuminate\Support\Js::from($resolvedFields) }},
            fieldValues: {{ Illuminate\Support\Js::from(old('fields', $fieldValues)) }},
            async loadDynamicFields() {
                if (! this.categoryId) { this.dynamicFields = []; this.fieldValues = {}; return; }
                const res = await fetch(`{{ url('/api/categories') }}/${this.categoryId}/fields`);
                this.dynamicFields = await res.json();
                this.fieldValues = {};
            },
            async loadBuildings(keep = false) {
                this.buildings = [];
                if (! this.locationId) { this.floors = []; this.rooms = []; return; }
                const res = await fetch(`{{ url('/api/buildings') }}?location_id=${this.locationId}`);
                this.buildings = await res.json();
                if (! keep) { this.buildingId = ''; this.floors = []; this.floorId = ''; this.rooms = []; this.roomId = ''; }
            },
            async loadFloors(keep = false) {
                this.floors = [];
                if (! this.buildingId) { this.rooms = []; return; }
                const res = await fetch(`{{ url('/api/floors') }}?building_id=${this.buildingId}`);
                this.floors = await res.json();
                if (! keep) { this.floorId = ''; this.rooms = []; this.roomId = ''; }
            },
            async loadRooms(keep = false) {
                this.rooms = [];
                if (! this.floorId) return;
                const res = await fetch(`{{ url('/api/rooms') }}?floor_id=${this.floorId}`);
                this.rooms = await res.json();
                if (! keep) { this.roomId = ''; }
            },
            async init() {
                if (this.locationId) {
                    await this.loadBuildings(true);
                    if (this.buildingId) {
                        await this.loadFloors(true);
                        if (this.floorId) await this.loadRooms(true);
                    }
                }
            },
         }">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ $asset->exists ? route('assets.show', $asset) : route('assets.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ $asset->exists ? 'Edit Asset' : 'New Asset' }}
            </h1>
        </div>

        <form method="POST"
              action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}"
              class="space-y-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @if($asset->exists) @method('PUT') @endif

            {{-- Identity --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Asset Name *" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $asset->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="company_id" value="Company *" />
                    @if($canChangeCompany)
                        <select id="company_id" name="company_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" @selected(old('company_id', $asset->company_id) == $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" disabled value="{{ $asset->company?->name }}"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-400 shadow-sm text-sm" />
                        <p class="mt-1 text-xs text-gray-400">Company can only change via an approved inter-company transfer.</p>
                    @endif
                    <x-input-error :messages="$errors->get('company_id')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="category_id" value="Category *" />
                    @if($canChangeCategory)
                        <select id="category_id" name="category_id" x-model="categoryId" @change="loadDynamicFields()" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select —</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" disabled value="{{ $asset->category?->name }}"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-400 shadow-sm text-sm" />
                        <p class="mt-1 text-xs text-gray-400">Category is locked because this asset has saved custom field data.</p>
                    @endif
                    <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="asset_type_id" value="Type *" />
                    <select id="asset_type_id" name="asset_type_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">— Select —</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}" @selected(old('asset_type_id', $asset->asset_type_id) == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('asset_type_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="status_id" value="Status *" />
                    <select id="status_id" name="status_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">— Select —</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->id }}" @selected(old('status_id', $asset->status_id) == $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status_id')" class="mt-1" />
                </div>
            </div>

            @if($errors->get('fields.*'))
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 text-sm text-red-700 dark:text-red-400 space-y-1">
                    @foreach($errors->get('fields.*') as $key => $messages)
                        @php
                            $label = $resolvedFields->first(fn ($f) => $f->fieldKey === last(explode('.', $key)))?->label ?? $key;
                        @endphp
                        <p><span class="font-medium">{{ $label }}:</span> {{ implode(' ', $messages) }}</p>
                    @endforeach
                </div>
            @endif
            <x-dynamic-fields />

            {{-- Identification --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Identification</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="serial_number" value="Serial Number" />
                        <x-text-input id="serial_number" name="serial_number" class="mt-1 block w-full" :value="old('serial_number', $asset->serial_number)" />
                        <x-input-error :messages="$errors->get('serial_number')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="model" value="Model" />
                        <x-text-input id="model" name="model" class="mt-1 block w-full" :value="old('model', $asset->model)" />
                        <x-input-error :messages="$errors->get('model')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="manufacturer" value="Manufacturer" />
                        <x-text-input id="manufacturer" name="manufacturer" class="mt-1 block w-full" :value="old('manufacturer', $asset->manufacturer)" />
                        <x-input-error :messages="$errors->get('manufacturer')" class="mt-1" />
                    </div>
                </div>
                <div class="mt-4">
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('description', $asset->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>
            </div>

            {{-- Assignment --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Assignment</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="custodian_id" value="Custodian" />
                        <select id="custodian_id" name="custodian_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach($custodians as $user)
                                <option value="{{ $user->id }}" @selected(old('custodian_id', $asset->custodian_id) == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('custodian_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="department_id" value="Department" />
                        <select id="department_id" name="department_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id', $asset->department_id) == $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('department_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="branch_id" value="Branch" />
                        <select id="branch_id" name="branch_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id', $asset->branch_id) == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('branch_id')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- Location (cascading) --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Location</p>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <x-input-label for="location_id" value="Location" />
                        <select id="location_id" name="location_id" x-model="locationId" @change="loadBuildings()"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('location_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="building_id" value="Building" />
                        <select id="building_id" name="building_id" x-model="buildingId" @change="loadFloors()" :disabled="buildings.length === 0"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm disabled:opacity-50">
                            <option value="">— None —</option>
                            <template x-for="building in buildings" :key="building.id">
                                <option :value="building.id" x-text="building.name"></option>
                            </template>
                        </select>
                        <x-input-error :messages="$errors->get('building_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="floor_id" value="Floor" />
                        <select id="floor_id" name="floor_id" x-model="floorId" @change="loadRooms()" :disabled="floors.length === 0"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm disabled:opacity-50">
                            <option value="">— None —</option>
                            <template x-for="floor in floors" :key="floor.id">
                                <option :value="floor.id" x-text="floor.name"></option>
                            </template>
                        </select>
                        <x-input-error :messages="$errors->get('floor_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="room_id" value="Room" />
                        <select id="room_id" name="room_id" x-model="roomId" :disabled="rooms.length === 0"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm disabled:opacity-50">
                            <option value="">— None —</option>
                            <template x-for="room in rooms" :key="room.id">
                                <option :value="room.id" x-text="room.name"></option>
                            </template>
                        </select>
                        <x-input-error :messages="$errors->get('room_id')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- Financials --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Purchase & Warranty</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="purchase_date" value="Purchase Date" />
                        <x-text-input id="purchase_date" type="date" name="purchase_date" class="mt-1 block w-full" :value="old('purchase_date', optional($asset->purchase_date)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('purchase_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="purchase_cost" value="Purchase Cost" />
                        <x-text-input id="purchase_cost" type="number" step="0.01" name="purchase_cost" class="mt-1 block w-full" :value="old('purchase_cost', $asset->purchase_cost)" />
                        <x-input-error :messages="$errors->get('purchase_cost')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="vendor" value="Vendor" />
                        <x-text-input id="vendor" name="vendor" class="mt-1 block w-full" :value="old('vendor', $asset->vendor)" />
                        <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="warranty_expiry" value="Warranty Expiry" />
                        <x-text-input id="warranty_expiry" type="date" name="warranty_expiry" class="mt-1 block w-full" :value="old('warranty_expiry', optional($asset->warranty_expiry)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('warranty_expiry')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="amc_expiry" value="AMC Expiry" />
                        <x-text-input id="amc_expiry" type="date" name="amc_expiry" class="mt-1 block w-full" :value="old('amc_expiry', optional($asset->amc_expiry)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('amc_expiry')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div>
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" name="notes" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('notes', $asset->notes) }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-1" />
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ $asset->exists ? route('assets.show', $asset) : route('assets.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>
                    {{ $asset->exists ? 'Update Asset' : 'Create Asset' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
