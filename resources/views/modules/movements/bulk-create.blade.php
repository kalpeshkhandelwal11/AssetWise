<x-app-layout>
    @section('page-title', 'Bulk Movement')

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('assets.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Bulk Movement</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">One approval request covers every asset selected below</p>
        </div>
    </div>

    <div class="max-w-2xl bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $assets->count() }} asset(s) selected</p>
        <div class="flex flex-wrap gap-2 mb-6 pb-6 border-b border-gray-100 dark:border-gray-700">
            @foreach($assets as $a)
                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                    {{ $a->name }}
                </span>
            @endforeach
        </div>

        <form method="POST" action="{{ route('movements.bulk.store') }}" class="space-y-5"
              x-data="{
                  movementTypeId: '{{ old('movement_type_id') }}',
                  typeCodes: @json($movementTypes->pluck('code', 'id')),
                  get code() { return this.typeCodes[this.movementTypeId] ?? null; },
                  get needsCustodian() { return ['ASSIGNMENT', 'CUSTODIAN_CHANGE'].includes(this.code); },
                  get needsLocation() { return this.code === 'TRANSFER'; },
                  get needsCompany() { return this.code === 'INTER_COMPANY_TRANSFER'; },
                  get isReturn() { return this.code === 'RETURN'; },
              }">
            @csrf
            @foreach($assets as $a)
                <input type="hidden" name="asset_ids[]" value="{{ $a->id }}">
            @endforeach

            <div>
                <x-input-label for="movement_type_id" value="Movement Type *" />
                <select id="movement_type_id" name="movement_type_id" x-model="movementTypeId" required class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">
                    <option value="">Select type…</option>
                    @foreach($movementTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('movement_type_id')" class="mt-1" />
            </div>

            <div x-show="needsCustodian" x-cloak>
                <x-input-label for="to_custodian_id" value="New Custodian *" />
                <select id="to_custodian_id" name="to_custodian_id" class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">
                    <option value="">Select custodian…</option>
                    @foreach($custodians as $custodian)
                        <option value="{{ $custodian->id }}" @selected(old('to_custodian_id') == $custodian->id)>{{ $custodian->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('to_custodian_id')" class="mt-1" />
            </div>

            <p x-show="isReturn" x-cloak class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50 rounded-lg px-3 py-2">
                Every selected asset's current custodian will be cleared on approval.
            </p>

            <div x-show="needsLocation" x-cloak class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="to_location_id" value="New Location" />
                    <select id="to_location_id" name="to_location_id" class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">
                        <option value="">— No change —</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('to_location_id') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="to_department_id" value="New Department" />
                    <select id="to_department_id" name="to_department_id" class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">
                        <option value="">— No change —</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('to_department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-show="needsCompany" x-cloak>
                <x-input-label for="to_company_id" value="Destination Company *" />
                <select id="to_company_id" name="to_company_id" class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">
                    <option value="">Select company…</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('to_company_id') == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Submission fails if any selected asset is already owned by the destination company.</p>
                <x-input-error :messages="$errors->get('to_company_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">{{ old('notes') }}</textarea>
            </div>

            <x-input-error :messages="$errors->get('asset')" class="mt-1" />
            <x-input-error :messages="$errors->get('asset_ids')" class="mt-1" />

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('assets.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</a>
                <x-primary-button type="submit">Submit for Approval</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
