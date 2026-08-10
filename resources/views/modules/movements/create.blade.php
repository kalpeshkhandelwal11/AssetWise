<x-app-layout>
    @section('page-title', 'New Movement')

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('movements.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">New Movement</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Assignment, return, transfer, custodian change, or inter-company transfer — approval-gated</p>
        </div>
    </div>

    <div class="max-w-2xl bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
        <form method="POST" action="{{ route('movements.store') }}" class="space-y-5"
              x-data="{
                  movementTypeId: '{{ old('movement_type_id') }}',
                  {{-- @js, not @json: Blade's @json splits its expression on commas, so the
                       comma inside pluck('code', 'id') would be parsed as the flags argument,
                       dropping JSON_HEX_QUOT and emitting raw quotes that terminate this
                       x-data attribute (breaking the whole component). --}}
                  typeCodes: @js($movementTypes->pluck('code', 'id')),
                  get code() { return this.typeCodes[this.movementTypeId] ?? null; },
                  get needsCustodian() { return ['ASSIGNMENT', 'CUSTODIAN_CHANGE'].includes(this.code); },
                  get needsLocation() { return this.code === 'TRANSFER'; },
                  get needsCompany() { return this.code === 'INTER_COMPANY_TRANSFER'; },
                  get isReturn() { return this.code === 'RETURN'; },
              }">
            @csrf

            {{-- WorkflowService::submit() reports configuration problems under the 'workflow'
                 key, which matches no field on this form — without this block a missing or
                 deactivated 'transfer' workflow makes the submission fail silently. --}}
            @error('workflow')
                <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 text-sm">
                    {{ $message }}
                    <span class="block mt-1 text-xs opacity-80">An administrator must activate a <strong>transfer</strong> workflow under Administration &rarr; Workflows before movements can be submitted.</span>
                </div>
            @enderror

            <div>
                <x-input-label for="asset_id" value="Asset *" />
                <select id="asset_id" name="asset_id" required class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">
                    <option value="">Select asset…</option>
                    @foreach($assets as $a)
                        <option value="{{ $a->id }}" @selected(old('asset_id', $asset?->id) == $a->id)>{{ $a->name }} @if($a->asset_tag)({{ $a->asset_tag }})@endif</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('asset_id')" class="mt-1" />
            </div>

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
                The asset's current custodian will be cleared on approval.
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
                <x-input-error :messages="$errors->get('to_location_id')" class="col-span-2" />
            </div>

            <div x-show="needsCompany" x-cloak>
                <x-input-label for="to_company_id" value="Destination Company *" />
                <select id="to_company_id" name="to_company_id" class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">
                    <option value="">Select company…</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('to_company_id') == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">The asset's current company is excluded automatically on submit.</p>
                <x-input-error :messages="$errors->get('to_company_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-1" />
            </div>

            <x-input-error :messages="$errors->get('asset')" class="mt-1" />

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('movements.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</a>
                <x-primary-button type="submit">Submit for Approval</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
