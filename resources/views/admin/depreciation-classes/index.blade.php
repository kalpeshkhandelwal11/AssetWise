<x-app-layout>
    @section('page-title', 'Schedule II Classes')

    <div class="max-w-4xl space-y-6">
        <x-breadcrumb :items="[['label' => 'Administration'], ['label' => 'Schedule II Classes']]" class="mb-2" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Companies Act 2013, Schedule II. Each asset category maps to a class (Admin → Categories); the class supplies the
                default useful life for straight-line depreciation (rate = 100 ÷ life). Non-depreciable classes (Land) are never depreciated.
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-4 py-3">Class</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Useful life (yrs)</th>
                        <th class="px-4 py-3">SLM rate</th>
                        <th class="px-4 py-3">Depreciable</th>
                        <th class="px-4 py-3">Categories</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($classes as $class)
                        <tr x-data="{ editing: false }" class="align-top">
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100" colspan="7" x-show="editing" x-cloak>
                                <form method="POST" action="{{ route('admin.depreciation-classes.update', $class) }}" class="flex flex-wrap items-end gap-3">
                                    @csrf
                                    @method('PUT')
                                    <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 w-56" :value="$class->name" required /></div>
                                    <div><x-input-label value="Code" /><x-text-input name="code" class="mt-1 w-40" :value="$class->code" required /></div>
                                    <div><x-input-label value="Life (yrs)" /><x-text-input name="useful_life_years" type="number" step="0.5" min="0.5" class="mt-1 w-24" :value="$class->useful_life_years" /></div>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pb-2">
                                        <input type="checkbox" name="is_depreciable" value="1" @checked($class->is_depreciable) class="rounded border-gray-300 text-indigo-600"> Depreciable
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pb-2">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($class->is_active) class="rounded border-gray-300 text-indigo-600"> Active
                                    </label>
                                    <x-primary-button>Save</x-primary-button>
                                    <button type="button" @click="editing = false" class="text-sm text-gray-500 pb-2">Cancel</button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100" x-show="! editing">
                                {{ $class->name }}
                                @unless($class->is_active)<span class="text-xs text-gray-400">(inactive)</span>@endunless
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500" x-show="! editing">{{ $class->code }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300" x-show="! editing">{{ $class->useful_life_years ? rtrim(rtrim(number_format($class->useful_life_years, 2), '0'), '.') : '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300" x-show="! editing">{{ $class->ratePercent() ? rtrim(rtrim(number_format($class->ratePercent(), 2), '0'), '.') . '%' : '—' }}</td>
                            <td class="px-4 py-3" x-show="! editing">
                                <x-status-badge :color="$class->is_depreciable ? 'green' : 'gray'" :label="$class->is_depreciable ? 'Yes' : 'No'" :dot="false" />
                            </td>
                            <td class="px-4 py-3 text-gray-500" x-show="! editing">{{ $class->categories_count }}</td>
                            <td class="px-4 py-3 text-right" x-show="! editing">
                                <button type="button" @click="editing = true" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Add class</p>
            <form method="POST" action="{{ route('admin.depreciation-classes.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 w-56" :value="old('name')" required /></div>
                <div><x-input-label value="Code" /><x-text-input name="code" class="mt-1 w-40" :value="old('code')" required /></div>
                <div><x-input-label value="Life (yrs)" /><x-text-input name="useful_life_years" type="number" step="0.5" min="0.5" class="mt-1 w-24" :value="old('useful_life_years')" /></div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pb-2">
                    <input type="checkbox" name="is_depreciable" value="1" checked class="rounded border-gray-300 text-indigo-600"> Depreciable
                </label>
                <x-primary-button>Add</x-primary-button>
            </form>
            <x-input-error :messages="$errors->all()" class="mt-2" />
        </div>
    </div>
</x-app-layout>
