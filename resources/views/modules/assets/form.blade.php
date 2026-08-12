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
            // Assignment auto-fill: custodian (employee) -> department + branch.
            custodianId: '{{ old('custodian_id', $asset->custodian_id) }}',
            departmentId: '{{ old('department_id', $asset->department_id) }}',
            branchId: '{{ old('branch_id', $asset->branch_id) }}',
            custodianMap: {{ Illuminate\Support\Js::from($custodians->keyBy('id')->map(fn ($e) => ['department_id' => $e->department_id, 'branch_id' => $e->branch_id])) }},
            // Purchase: asset life (years) -> EOL projected date.
            purchaseDate: '{{ old('purchase_date', optional($asset->purchase_date)->format('Y-m-d')) }}',
            usefulLife: '{{ old('useful_life_years', $asset->useful_life_years) }}',
            eolDate: '{{ old('eol_projected_date', optional($asset->eol_projected_date)->format('Y-m-d')) }}',
            // Media at creation (repeatable rows of file + label).
            mediaRows: [{}],
            applyCustodian() {
                const e = this.custodianMap[this.custodianId];
                if (e) { this.departmentId = e.department_id ?? ''; this.branchId = e.branch_id ?? ''; }
            },
            computeEol() {
                if (this.purchaseDate && this.usefulLife) {
                    const d = new Date(this.purchaseDate);
                    d.setFullYear(d.getFullYear() + parseInt(this.usefulLife));
                    this.eolDate = d.toISOString().slice(0, 10);
                }
            },
            addMediaRow() { this.mediaRows.push({}); },
            removeMediaRow(i) { this.mediaRows.splice(i, 1); if (this.mediaRows.length === 0) this.mediaRows.push({}); },
            async loadDynamicFields() {
                if (! this.categoryId) { this.dynamicFields = []; this.fieldValues = {}; return; }
                const res = await fetch(`{{ url('/api/categories') }}/${this.categoryId}/fields`);
                this.dynamicFields = await res.json();
                this.fieldValues = {};
            },
            // Re-fetch the category's fields WITHOUT clearing entered values — used by the
            // "Refresh" affordance after a field is added via the builder in another tab.
            async refreshFields() {
                if (! this.categoryId) return;
                const res = await fetch(`{{ url('/api/categories') }}/${this.categoryId}/fields`);
                this.dynamicFields = await res.json();
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
              enctype="multipart/form-data"
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

            @if(! $asset->exists)
                <div class="sm:w-1/2">
                    <x-input-label for="asset_tag" value="Asset ID" />
                    @if($assetNamingEnabled)
                        <input type="text" disabled value="Auto-generated ({{ $assetNamingPreview }})"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-400 shadow-sm text-sm font-mono" />
                        <p class="mt-1 text-xs text-gray-400">Assigned automatically from the naming series on save.</p>
                    @else
                        <x-text-input id="asset_tag" name="asset_tag" class="mt-1 block w-full font-mono" :value="old('asset_tag')" placeholder="e.g. AST-0001" />
                        <x-input-error :messages="$errors->get('asset_tag')" class="mt-1" />
                    @endif
                </div>
            @endif

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

            {{-- Asset Attribute --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Asset Attribute</p>
                    @can('category_fields.manage')
                        <div class="flex items-center gap-2">
                            <a :href="categoryId ? '{{ url('admin/categories') }}/' + categoryId + '/fields' : '#'"
                               target="_blank" rel="noopener"
                               :class="!categoryId && 'opacity-40 pointer-events-none'"
                               class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Attribute
                            </a>
                            <button type="button" @click="refreshFields()" :class="!categoryId && 'opacity-40 pointer-events-none'"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400" title="Reload custom fields">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Refresh
                            </button>
                        </div>
                    @endcan
                </div>
                <p class="text-xs text-gray-400 mb-3 -mt-1">Select a category, then use <span class="font-medium">Add Attribute</span> to define custom fields for it (opens in a new tab); click <span class="font-medium">Refresh</span> to load them here.</p>
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
                        <select id="custodian_id" name="custodian_id" x-model="custodianId" @change="applyCustodian()"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach($custodians as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Department &amp; branch auto-fill from the selected employee.</p>
                        <x-input-error :messages="$errors->get('custodian_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="department_id" value="Department" />
                        <select id="department_id" name="department_id" x-model="departmentId"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('department_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="branch_id" value="Branch (Site)" />
                        <select id="branch_id" name="branch_id" x-model="branchId"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— None —</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
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
                        <input id="purchase_date" type="date" name="purchase_date" x-model="purchaseDate" @change="computeEol()"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
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
                        <x-input-label for="vendor_invoice_no" value="Vendor Invoice No." />
                        <x-text-input id="vendor_invoice_no" name="vendor_invoice_no" class="mt-1 block w-full" :value="old('vendor_invoice_no', $asset->vendor_invoice_no)" />
                        <x-input-error :messages="$errors->get('vendor_invoice_no')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="useful_life_years" value="Asset Life (years)" />
                        <select id="useful_life_years" name="useful_life_years" x-model="usefulLife" @change="computeEol()"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">—</option>
                            @for($y = 1; $y <= 20; $y++)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Auto-fills EOL date from purchase date.</p>
                        <x-input-error :messages="$errors->get('useful_life_years')" class="mt-1" />
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
                    <div>
                        <x-input-label for="eol_projected_date" value="EOL Projected Date" />
                        <input id="eol_projected_date" type="date" name="eol_projected_date" x-model="eolDate"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        <x-input-error :messages="$errors->get('eol_projected_date')" class="mt-1" />
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input type="checkbox" id="is_eol" name="is_eol" value="1" @checked(old('is_eol', $asset->is_eol)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <x-input-label for="is_eol" value="End of Life" />
                        <x-input-error :messages="$errors->get('is_eol')" class="mt-1" />
                    </div>
                </div>
            </div>

            @if(! $asset->exists)
                {{-- Media (labelled attachments captured at creation) --}}
                <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Media</p>
                    <template x-for="(row, i) in mediaRows" :key="i">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="file" name="media[]" accept="image/*,application/pdf" capture="environment"
                                   class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                            <select name="media_labels[]"
                                    class="w-48 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="photo">Asset Photo</option>
                                <option value="invoice">Invoice</option>
                                <option value="warranty_card">Warranty Card</option>
                                <option value="manual">Manual</option>
                                <option value="agreement">Agreement</option>
                            </select>
                            <button type="button" @click="removeMediaRow(i)" class="p-1.5 text-gray-400 hover:text-red-600" title="Remove">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                    <button type="button" @click="addMediaRow()" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add media
                    </button>
                    <x-input-error :messages="$errors->get('media.0')" class="mt-1" />
                </div>

                @can('tags.assign')
                    {{-- Barcode / Tag (assign an available pool tag at creation) --}}
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Barcode / Tag</p>
                        <div class="sm:w-1/3">
                            <x-input-label for="tag_id" value="Assign Tag" />
                            <select id="tag_id" name="tag_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">— None —</option>
                                @foreach($availableTags as $tag)
                                    <option value="{{ $tag->id }}" @selected(old('tag_id') == $tag->id)>{{ $tag->tag_number }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Optional — assign a printed tag from the available pool.</p>
                            <x-input-error :messages="$errors->get('tag_id')" class="mt-1" />
                        </div>
                    </div>
                @endcan
            @endif

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
