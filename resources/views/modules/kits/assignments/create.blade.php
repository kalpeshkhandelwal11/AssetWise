<x-app-layout>
    @section('page-title', 'Assign a Kit / Bundle')

    <div class="max-w-2xl">
        <x-breadcrumb :items="[
            ['label' => 'Kits', 'url' => route('kits.index')],
            ['label' => 'Assign'],
        ]" class="mb-4" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <p class="text-xs text-gray-400 mb-4">Approval mode: <strong>{{ $mode === 'per_asset' ? 'Per asset' : 'Single (one approval for the whole kit)' }}</strong> — change under Admin → Kit Settings.</p>

            {{-- WorkflowService reports config errors under the "workflow" key, which matches no field. --}}
            @error('workflow')
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400">{{ $message }}</div>
            @enderror
            @error('asset_ids')
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('kit-assignments.store') }}" class="space-y-5"
                  x-data="{
                      source: '{{ old('kit_id') ? 'kit' : 'adhoc' }}',
                      movementType: '{{ old('movement_type_id') }}',
                      typeCodes: @js($movementTypes->pluck('code', 'id')),
                      get code() { return this.typeCodes[this.movementType] || '' },
                  }">
                @csrf

                <div>
                    <x-input-label value="What to assign" />
                    <div class="flex gap-4 mt-1">
                        <label class="flex items-center gap-2 text-sm"><input type="radio" x-model="source" value="kit" class="text-indigo-600"> Saved kit</label>
                        <label class="flex items-center gap-2 text-sm"><input type="radio" x-model="source" value="adhoc" class="text-indigo-600"> Ad-hoc bundle</label>
                    </div>
                </div>

                <div x-show="source === 'kit'">
                    <x-input-label for="kit_id" value="Kit" />
                    <select id="kit_id" name="kit_id" x-bind:disabled="source !== 'kit'"
                            class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">— Select a kit —</option>
                        @foreach($kits as $kit)
                            <option value="{{ $kit->id }}" @selected(old('kit_id', $preselectedKit?->id) == $kit->id)>{{ $kit->name }} ({{ $kit->items->flatMap->kitAssets->count() }} assets)</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">The kit's linked slot assets are assigned together.</p>
                </div>

                <div x-show="source === 'adhoc'">
                    <x-input-label for="asset_ids" value="Assets" />
                    <select id="asset_ids" name="asset_ids[]" multiple size="8" x-bind:disabled="source !== 'adhoc'"
                            class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->asset_tag }})</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Ctrl/Cmd-click to select multiple.</p>
                </div>

                <div>
                    <x-input-label for="movement_type_id" value="Movement Type" />
                    <select id="movement_type_id" name="movement_type_id" x-model="movementType" required
                            class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">— Select —</option>
                        @foreach($movementTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('movement_type_id')" class="mt-1" />
                </div>

                {{-- Destination fields shown per movement-type code (mirrors M09's assertMovable rules). --}}
                <div x-show="['ASSIGNMENT', 'CUSTODIAN_CHANGE'].includes(code)">
                    <x-input-label for="to_custodian_id" value="Custodian" />
                    <select id="to_custodian_id" name="to_custodian_id" class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">— Select —</option>
                        @foreach($custodians as $u)
                            <option value="{{ $u->id }}" @selected(old('to_custodian_id') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('to_custodian_id')" class="mt-1" />
                </div>

                <div x-show="code === 'TRANSFER'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="to_location_id" value="Location" />
                        <select id="to_location_id" name="to_location_id" class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">— Select —</option>
                            @foreach($locations as $l)
                                <option value="{{ $l->id }}" @selected(old('to_location_id') == $l->id)>{{ $l->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="to_department_id" value="Department" />
                        <select id="to_department_id" name="to_department_id" class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">— Select —</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" @selected(old('to_department_id') == $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-input-error :messages="$errors->get('to_location_id')" class="mt-1 sm:col-span-2" />
                </div>

                <div x-show="code === 'INTER_COMPANY_TRANSFER'">
                    <x-input-label for="to_company_id" value="Destination Company" />
                    <select id="to_company_id" name="to_company_id" class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">— Select —</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" @selected(old('to_company_id') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('to_company_id')" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <a href="{{ route('kits.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700">Cancel</a>
                    <x-primary-button>Submit for Approval</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
