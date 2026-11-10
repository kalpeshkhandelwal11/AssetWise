<x-app-layout>
    @section('page-title', 'Plaza Handover')

    <div class="max-w-3xl">
        <x-breadcrumb :items="[['label' => 'NHAI / Toll Plazas', 'url' => route('nhai.index', ['tab' => 'handovers'])], ['label' => 'New handover']]" class="mb-4" />

        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Plaza handover (contract end)</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Lists every asset at the plaza. NHAI's assets are returned; for ours you choose: sell to a party, give to NHAI (written off — the default), or move to another plaza.
                One approval covers the whole handover.
            </p>
        </div>

        <form method="POST" action="{{ route('nhai.handovers.store') }}" enctype="multipart/form-data"
              class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <x-input-label for="location_id" value="Toll plaza *" />
                    <select id="location_id" name="location_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        <option value="">— Select —</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('location_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="company_id" value="Our company handing over *" />
                    <select id="company_id" name="company_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('company_id')" class="mt-1" />
                    <x-input-error :messages="$errors->get('authority_company_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="handover_date" value="Handover date *" />
                    <x-text-input id="handover_date" name="handover_date" type="date" class="mt-1 block w-full" required :value="old('handover_date', now()->format('Y-m-d'))" />
                </div>
                <div>
                    <x-input-label for="document_no" value="Handover document no." />
                    <x-text-input id="document_no" name="document_no" class="mt-1 block w-full" :value="old('document_no')" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="document" value="Signed handover document (PDF / image)" />
                    <input id="document" name="document" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-400">
                </div>
            </div>

            @include('modules.nhai.partials.signatories', ['values' => []])

            <div>
                <x-input-label for="remarks" value="Remarks" />
                <textarea id="remarks" name="remarks" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">{{ old('remarks') }}</textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('nhai.index', ['tab' => 'handovers']) }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700">Cancel</a>
                <x-primary-button>Create & list assets</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
