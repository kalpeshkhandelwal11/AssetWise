<x-app-layout>
    @section('page-title', 'Category Depreciation Defaults')

    <div class="max-w-2xl">
        <x-breadcrumb :items="[
            ['label' => 'Categories', 'url' => route('admin.categories.index')],
            ['label' => $category->name],
            ['label' => 'Depreciation Defaults'],
        ]" class="mb-4" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                Assets in <span class="font-medium text-gray-700 dark:text-gray-300">{{ $category->name }}</span> inherit these defaults when depreciation is first configured. A salvage (residual) value is required — {{ \App\Models\Setting::get('depreciation_default_salvage_percent', '5') }}% of cost is the Companies Act default.
            </p>

            <form method="POST" action="{{ route('admin.categories.depreciation.update', $category) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="depreciation_method_id" value="Method" />
                    <select id="depreciation_method_id" name="depreciation_method_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">— Select —</option>
                        @foreach($methods as $method)
                            <option value="{{ $method->id }}" @selected(old('depreciation_method_id', $default?->depreciation_method_id) == $method->id)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('depreciation_method_id')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <x-input-label for="useful_life_months" value="Useful Life (months)" />
                        @php $depClass = $category->effectiveDepreciationClass(); @endphp
                        <x-text-input id="useful_life_months" name="useful_life_months" type="number" min="1" class="mt-1 block w-full" :value="old('useful_life_months', $default?->useful_life_months ?? $depClass?->usefulLifeMonths())" required />
                        @if($depClass)
                            <p class="mt-1 text-xs text-gray-400">
                                Schedule II: {{ $depClass->name }} —
                                {{ $depClass->is_depreciable ? $depClass->usefulLifeMonths() . ' months (' . rtrim(rtrim(number_format($depClass->ratePercent(), 2), '0'), '.') . '% SLM)' : 'not depreciated' }}
                            </p>
                        @endif
                        <x-input-error :messages="$errors->get('useful_life_months')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="start_basis" value="Start Basis" />
                        <select id="start_basis" name="start_basis"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            @foreach(['purchase_date' => 'Purchase Date', 'commission_date' => 'Commission Date'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('start_basis', $default?->start_basis ?? 'purchase_date') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('start_basis')" class="mt-1" />
                    </div>
                    @include('modules.depreciation.partials.salvage-fields', [
                        'salvagePercent' => $default?->salvage_percent,
                        'salvageValue'   => $default?->salvage_percent === null ? $default?->salvage_value : null,
                    ])
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <a href="{{ route('admin.categories.index') }}"
                       class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                        Cancel
                    </a>
                    <x-primary-button>Save Defaults</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
