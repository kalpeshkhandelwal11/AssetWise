<x-app-layout>
    @section('page-title', 'Asset Depreciation')

    @php
        $startDefault = $setting?->start_date
            ? $setting->start_date->format('Y-m-d')
            : ($defaults['start_date'] ?? optional($asset->purchase_date)->format('Y-m-d'));
        $prefill = [
            'method'          => old('depreciation_method_id', $setting?->depreciation_method_id ?? ($defaults['depreciation_method_id'] ?? null)),
            'useful_life'     => old('useful_life_months', $setting?->useful_life_months ?? ($defaults['useful_life_months'] ?? null)),
            'salvage_value'   => old('salvage_value', $setting?->salvage_value ?? ($defaults['salvage_value'] ?? null)),
            'salvage_percent' => old('salvage_percent', $setting?->salvage_percent ?? ($defaults['salvage_percent'] ?? null)),
            'start_date'      => old('start_date', $startDefault),
            'cost_basis'      => old('cost_basis', $setting?->cost_basis ?? $asset->purchase_cost),
        ];
    @endphp

    <div class="max-w-2xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => $asset->name, 'url' => route('assets.show', $asset)],
            ['label' => 'Depreciation'],
        ]" class="mb-4" />

        @if($setting)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Depreciation</p>
                    <a href="{{ route('assets.depreciation.schedule', $asset) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">View schedule</a>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Method</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $setting->method?->name }}</dd></div>
                    <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Useful Life</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $setting->useful_life_months }} months</dd></div>
                    <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Cost Basis</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ number_format($setting->cost_basis, 2) }}</dd></div>
                    <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Salvage Value</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ number_format($setting->salvage_value, 2) }}</dd></div>
                    <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Accumulated</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ number_format($setting->accumulated_depreciation, 2) }}</dd></div>
                    <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Book Value</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5 font-semibold">{{ number_format($setting->current_book_value, 2) }}</dd></div>
                </dl>
            </div>
        @endif

        @if($pending)
            <div class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-900/20 p-4 text-sm text-amber-800 dark:text-amber-300">
                A depreciation change is pending approval.
                @if($pending->approvalRequest)
                    <a href="{{ route('approvals.show', $pending->approvalRequest) }}" class="underline">View progress</a>
                @endif
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $setting ? 'Request a Change' : 'Configure Depreciation' }}</p>
            <p class="text-xs text-gray-400 mb-5">Changes are applied only after approval.</p>

            {{-- WorkflowService reports config errors under the "workflow" key, which matches no field. --}}
            @error('workflow')
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400">{{ $message }}</div>
            @enderror
            @error('asset')
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-400">{{ $message }}</div>
            @enderror

            @if(! $methods->count())
                <p class="text-sm text-gray-500 dark:text-gray-400">No active depreciation methods are configured. An administrator must activate one first.</p>
            @else
                <div class="mb-4 p-3 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 text-xs">
                    These values are pre-filled from the <span class="font-medium">category's default</span>. Anything you set here <span class="font-medium">overrides the category for this asset only</span> (per-asset settings take precedence). Changes go through depreciation approval before taking effect.
                </div>
                <form method="POST" action="{{ route('assets.depreciation.update', $asset) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="depreciation_method_id" value="Method" />
                        <select id="depreciation_method_id" name="depreciation_method_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select —</option>
                            @foreach($methods as $method)
                                <option value="{{ $method->id }}" @selected($prefill['method'] == $method->id)>{{ $method->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('depreciation_method_id')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="cost_basis" value="Cost Basis" />
                            <x-text-input id="cost_basis" name="cost_basis" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$prefill['cost_basis']" required />
                            <x-input-error :messages="$errors->get('cost_basis')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="useful_life_months" value="Useful Life (months)" />
                            <x-text-input id="useful_life_months" name="useful_life_months" type="number" min="1" class="mt-1 block w-full" :value="$prefill['useful_life']" required />
                            <x-input-error :messages="$errors->get('useful_life_months')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="start_date" value="Start Date" />
                            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="$prefill['start_date']" required />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                        </div>
                        <div></div>
                        <div>
                            <x-input-label for="salvage_value" value="Salvage Value (fixed)" />
                            <x-text-input id="salvage_value" name="salvage_value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$prefill['salvage_value']" />
                            <x-input-error :messages="$errors->get('salvage_value')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="salvage_percent" value="Salvage Percent (%)" />
                            <x-text-input id="salvage_percent" name="salvage_percent" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full" :value="$prefill['salvage_percent']" />
                            <x-input-error :messages="$errors->get('salvage_percent')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <a href="{{ route('assets.show', $asset) }}"
                           class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                            Cancel
                        </a>
                        <x-primary-button>Submit for Approval</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
