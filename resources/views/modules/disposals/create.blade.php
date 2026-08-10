<x-app-layout>
    @section('page-title', 'New Disposal Request')

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('disposals.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">New Disposal Request</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Requires full approval before write-off and scrap completion</p>
        </div>
    </div>

    <div class="max-w-2xl bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
        <form method="POST" action="{{ route('disposals.store') }}" class="space-y-5">
            @csrf

            {{-- WorkflowService::submit() reports configuration problems under the 'workflow'
                 key, which matches no field on this form — without this block the submission
                 fails silently. 'disposal' ships with no seeded workflow by design (M08), so
                 this is the expected first-run experience, not an edge case. --}}
            @error('workflow')
                <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 text-sm">
                    {{ $message }}
                    <span class="block mt-1 text-xs opacity-80">An administrator must activate a <strong>disposal</strong> workflow under Administration &rarr; Workflows before disposal requests can be submitted.</span>
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
                <x-input-label for="disposal_type_id" value="Disposal Type *" />
                <select id="disposal_type_id" name="disposal_type_id" required class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">
                    <option value="">Select type…</option>
                    @foreach($disposalTypes as $type)
                        <option value="{{ $type->id }}" @selected(old('disposal_type_id') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('disposal_type_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="reason" value="Reason *" />
                <textarea id="reason" name="reason" rows="4" required class="mt-1 block w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500">{{ old('reason') }}</textarea>
                <x-input-error :messages="$errors->get('reason')" class="mt-1" />
            </div>

            <p class="text-xs text-gray-400">
                To attach supporting documents, upload them to the asset's Attachments tab before or after submitting this request.
            </p>

            <x-input-error :messages="$errors->get('asset')" class="mt-1" />

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('disposals.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</a>
                <x-primary-button type="submit">Submit for Approval</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
