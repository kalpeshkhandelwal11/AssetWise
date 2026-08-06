@php
    $vr = $field->validation_rules ?? [];
    $existingOptions = $field->exists ? $field->options->map(fn ($o) => ['value' => $o->option_value, 'label' => $o->option_label])->values()->all() : [];
@endphp
<x-app-layout>
    @section('page-title', ($field->exists ? 'Edit Field' : 'New Field') . ' — ' . $category->name)

    <div class="max-w-2xl" x-data="{
        fieldType: '{{ old('field_type', $field->field_type ?? 'text') }}',
        options: {{ Illuminate\Support\Js::from(old('options', $existingOptions)) }},
        addOption() { this.options.push({ value: '', label: '' }); },
        removeOption(i) { this.options.splice(i, 1); },
    }">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.categories.fields.index', $category) }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                {{ $field->exists ? 'Edit Field' : 'New Field' }}
            </h1>
        </div>

        <form method="POST"
              action="{{ $field->exists ? route('admin.categories.fields.update', [$category, $field]) : route('admin.categories.fields.store', $category) }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @if($field->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="label" value="Label *" />
                    <x-text-input id="label" name="label" class="mt-1 block w-full" :value="old('label', $field->label)" required autofocus />
                    <x-input-error :messages="$errors->get('label')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="field_key" value="Field Key *" />
                    <x-text-input id="field_key" name="field_key" class="mt-1 block w-full font-mono"
                                  :value="old('field_key', $field->field_key)" placeholder="e.g. cpu_cores" required />
                    <p class="mt-1 text-xs text-gray-400">Lowercase letters, numbers, underscores. Must be unique across this category's ancestors and descendants.</p>
                    <x-input-error :messages="$errors->get('field_key')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="field_type" value="Field Type *" />
                    <select id="field_type" name="field_type" x-model="fieldType" required
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        @foreach(['text' => 'Text', 'textarea' => 'Textarea', 'number' => 'Number', 'date' => 'Date', 'boolean' => 'Boolean', 'dropdown' => 'Dropdown'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('field_type', $field->field_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($field->exists)
                        <p class="mt-1 text-xs text-gray-400">Cannot change once assets have saved values for this field.</p>
                    @endif
                    <x-input-error :messages="$errors->get('field_type')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="display_order" value="Display Order" />
                    <x-text-input id="display_order" type="number" name="display_order" class="mt-1 block w-full" :value="old('display_order', $field->display_order ?? 0)" />
                    <x-input-error :messages="$errors->get('display_order')" class="mt-1" />
                </div>
            </div>

            <div class="flex items-center gap-6">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $field->is_required)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Required
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="is_searchable" value="1" @checked(old('is_searchable', $field->is_searchable)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Searchable
                </label>
            </div>

            {{-- Dropdown options --}}
            <div x-show="fieldType === 'dropdown'" class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Options</p>
                <template x-for="(option, i) in options" :key="i">
                    <div class="flex items-center gap-2 mb-2">
                        <input type="text" :name="'options[' + i + '][value]'" x-model="option.value" placeholder="Stored value"
                               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 flex-1">
                        <input type="text" :name="'options[' + i + '][label]'" x-model="option.label" placeholder="Display label"
                               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 flex-1">
                        <button type="button" @click="removeOption(i)" class="p-1.5 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
                <button type="button" @click="addOption()" class="text-sm text-indigo-600 hover:text-indigo-700">+ Add option</button>
                <x-input-error :messages="$errors->get('options')" class="mt-1" />
            </div>

            {{-- Validation rules --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Validation</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div x-show="fieldType === 'number'">
                        <x-input-label for="validation_min" value="Min" />
                        <x-text-input id="validation_min" type="number" step="any" name="validation_min" class="mt-1 block w-full" :value="old('validation_min', $vr['min'] ?? null)" />
                    </div>
                    <div x-show="fieldType === 'number'">
                        <x-input-label for="validation_max" value="Max" />
                        <x-text-input id="validation_max" type="number" step="any" name="validation_max" class="mt-1 block w-full" :value="old('validation_max', $vr['max'] ?? null)" />
                    </div>
                    <div x-show="fieldType === 'number'">
                        <x-input-label for="validation_decimal_places" value="Decimal Places" />
                        <x-text-input id="validation_decimal_places" type="number" name="validation_decimal_places" class="mt-1 block w-full" :value="old('validation_decimal_places', $vr['decimal_places'] ?? null)" />
                    </div>
                    <div x-show="fieldType === 'text' || fieldType === 'textarea'">
                        <x-input-label for="validation_max_length" value="Max Length" />
                        <x-text-input id="validation_max_length" type="number" name="validation_max_length" class="mt-1 block w-full" :value="old('validation_max_length', $vr['max_length'] ?? null)" />
                    </div>
                    <div x-show="fieldType === 'text'">
                        <x-input-label for="validation_regex" value="Regex Pattern" />
                        <x-text-input id="validation_regex" name="validation_regex" class="mt-1 block w-full font-mono" :value="old('validation_regex', $vr['regex'] ?? null)" />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('admin.categories.fields.index', $category) }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>
                    {{ $field->exists ? 'Update Field' : 'Create Field' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
