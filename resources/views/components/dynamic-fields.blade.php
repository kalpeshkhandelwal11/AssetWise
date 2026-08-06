{{-- Renders category-specific custom fields from the shared Alpine `dynamicFields`/`fieldValues` state
     defined on the parent form's x-data (see modules/assets/form.blade.php). --}}
<div x-show="dynamicFields.length > 0" class="pt-2 border-t border-gray-100 dark:border-gray-700">
    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Custom Fields</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <template x-for="field in dynamicFields" :key="field.id">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" x-text="field.label + (field.is_required ? ' *' : '')"></label>

                <template x-if="field.field_type === 'text'">
                    <input type="text" :name="'fields[' + field.field_key + ']'" x-model="fieldValues[field.field_key]"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </template>

                <template x-if="field.field_type === 'textarea'">
                    <textarea :name="'fields[' + field.field_key + ']'" x-model="fieldValues[field.field_key]" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                </template>

                <template x-if="field.field_type === 'number'">
                    <input type="number" step="any" :name="'fields[' + field.field_key + ']'" x-model="fieldValues[field.field_key]"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </template>

                <template x-if="field.field_type === 'date'">
                    <input type="date" :name="'fields[' + field.field_key + ']'" x-model="fieldValues[field.field_key]"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </template>

                <template x-if="field.field_type === 'boolean'">
                    <div class="mt-2">
                        <input type="hidden" :name="'fields[' + field.field_key + ']'" value="0">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" :name="'fields[' + field.field_key + ']'" value="1"
                                   :checked="fieldValues[field.field_key] == '1'"
                                   @change="fieldValues[field.field_key] = $event.target.checked ? '1' : '0'"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Yes
                        </label>
                    </div>
                </template>

                <template x-if="field.field_type === 'dropdown'">
                    <select :name="'fields[' + field.field_key + ']'" x-model="fieldValues[field.field_key]"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">— Select —</option>
                        <template x-for="option in field.options" :key="option.value">
                            <option :value="option.value" x-text="option.label"></option>
                        </template>
                    </select>
                </template>
            </div>
        </template>
    </div>
</div>
