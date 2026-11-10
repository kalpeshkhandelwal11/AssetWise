<x-app-layout>
    @php $isNhai = $takeover->kind === 'nhai'; @endphp
    @section('page-title', $takeover->exists ? 'Edit Takeover' : (\App\Models\PlazaTakeover::KINDS[$takeover->kind] ?? 'Takeover'))

    <div class="max-w-3xl">
        <x-breadcrumb :items="[['label' => 'NHAI / Toll Plazas', 'url' => route('nhai.index', ['tab' => 'takeovers'])], ['label' => $takeover->exists ? $takeover->reference_no : 'New takeover']]" class="mb-4" />

        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ \App\Models\PlazaTakeover::KINDS[$takeover->kind] }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                @if($isNhai)
                    Record the assets NHAI hands over with the plaza. They stay NHAI's property — no price, no depreciation — and are looked after by the plaza manager.
                @else
                    Assets we buy at the takeover (e.g. from the previous toll agency). Each line carries a price; they become ours and are depreciated.
                @endif
            </p>
        </div>

        <form method="POST" enctype="multipart/form-data"
              action="{{ $takeover->exists ? route('nhai.takeovers.update', $takeover) : route('nhai.takeovers.store') }}"
              class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
            @csrf
            @if($takeover->exists) @method('PUT') @else <input type="hidden" name="kind" value="{{ $takeover->kind }}"> @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <x-input-label for="location_id" value="Toll plaza *" />
                    <select id="location_id" name="location_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        <option value="">— Select —</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id', $takeover->location_id) == $location->id)>
                                {{ $location->name }}{{ $location->chainage ? ' · Ch '.$location->chainage : '' }}{{ $location->manager ? ' — manager '.$location->manager->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('location_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="takeover_date" value="Takeover date *" />
                    <x-text-input id="takeover_date" name="takeover_date" type="date" class="mt-1 block w-full" required
                                  :value="old('takeover_date', optional($takeover->takeover_date)->format('Y-m-d'))" />
                    <x-input-error :messages="$errors->get('takeover_date')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="from_company_id" :value="$isNhai ? 'Handed over by (authority) *' : 'Bought from *'" />
                    <select id="from_company_id" name="from_company_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        @foreach($parties->filter(fn ($p) => $isNhai ? $p->isAuthority() : true) as $party)
                            <option value="{{ $party->id }}" @selected(old('from_company_id', $takeover->from_company_id) == $party->id)>{{ $party->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">External parties are maintained in Administration → Companies (party type).</p>
                    <x-input-error :messages="$errors->get('from_company_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="company_id" value="Our operating company *" />
                    <select id="company_id" name="company_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id', $takeover->company_id) == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('company_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="custodian_id" value="Custodian (defaults to the plaza manager)" />
                    <select id="custodian_id" name="custodian_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        <option value="">— Plaza manager —</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(old('custodian_id', $takeover->custodian_id) == $employee->id)>{{ $employee->name }} ({{ $employee->employee_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="document_no" value="Handover document no." />
                    <x-text-input id="document_no" name="document_no" class="mt-1 block w-full" :value="old('document_no', $takeover->document_no)" placeholder="e.g. TP-02" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="document" value="Signed handover document (PDF / image)" />
                    <input id="document" name="document" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-400">
                    @if($takeover->document_path)
                        <a href="{{ Storage::url($takeover->document_path) }}" target="_blank" class="text-xs text-indigo-600 hover:underline">Current document</a>
                    @endif
                    <x-input-error :messages="$errors->get('document')" class="mt-1" />
                </div>
            </div>

            @include('modules.nhai.partials.signatories', ['values' => $takeover->signatories ?? []])

            <div>
                <x-input-label for="remarks" value="Remarks" />
                <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">{{ old('remarks', $takeover->remarks) }}</textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ $takeover->exists ? route('nhai.takeovers.show', $takeover) : route('nhai.index', ['tab' => 'takeovers']) }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700">Cancel</a>
                <x-primary-button>{{ $takeover->exists ? 'Save' : 'Create & add assets' }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
