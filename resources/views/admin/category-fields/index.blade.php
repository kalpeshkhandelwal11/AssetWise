<x-app-layout>
    @section('page-title', 'Custom Fields — ' . $category->name)

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.categories.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Custom Fields</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $category->name }}</p>
            </div>
        </div>
        <a href="{{ route('admin.categories.fields.create', $category) }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Field
        </a>
    </div>

    {{-- Fields defined on this category --}}
    <div class="mb-6">
    <x-data-table>
        <x-slot:header>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Defined on this category</p>
        </x-slot:header>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Label</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Key</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Required</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($ownFields as $field)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $field->label }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $field->field_key }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 capitalize">{{ $field->field_type }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $field->is_required ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.categories.fields.edit', [$category, $field]) }}"
                                       class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <x-confirm-modal :action="route('admin.categories.fields.destroy', [$category, $field])"
                                                      title="Delete this field?"
                                                      message="Existing saved values remain but become read-only."
                                                      trigger-class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                      trigger-title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </x-confirm-modal>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">No fields defined directly on this category.</td></tr>
                    @endforelse
                </tbody>
            </table>
    </x-data-table>
    </div>

    {{-- Inherited fields --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Inherited from ancestor categories</p>
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($inherited as $field)
                @php $override = $overrides->get($field->id); @endphp
                <div class="p-4" x-data="{ open: false, type: '{{ $override?->override_type ?? 'hide' }}' }">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $field->label }}
                                <span class="font-mono text-xs text-gray-400 ml-1">{{ $field->fieldKey }}</span>
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ ucfirst($field->fieldType) }} &middot; from {{ $ancestorNames[$field->definedOnCategoryId] ?? 'ancestor' }}
                                @if($override)
                                    &middot; <span class="text-amber-500">override: {{ str_replace('_', ' ', $override->override_type) }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="open = !open" type="button" class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                                {{ $override ? 'Edit override' : 'Override' }}
                            </button>
                            @if($override)
                                <form method="POST" action="{{ route('admin.categories.fields.override.destroy', [$category, $field->id]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800 transition-colors">
                                        Remove
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div x-show="open" x-transition class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                        <form method="POST" action="{{ route('admin.categories.fields.override.store', [$category, $field->id]) }}" class="space-y-3">
                            @csrf
                            <div class="flex gap-4 text-sm">
                                <label class="inline-flex items-center gap-1.5">
                                    <input type="radio" name="override_type" value="hide" x-model="type"> Hide
                                </label>
                                <label class="inline-flex items-center gap-1.5">
                                    <input type="radio" name="override_type" value="relabel" x-model="type"> Relabel
                                </label>
                                <label class="inline-flex items-center gap-1.5">
                                    <input type="radio" name="override_type" value="change_required" x-model="type"> Change required
                                </label>
                            </div>
                            <div x-show="type === 'relabel'">
                                <input type="text" name="override_label" placeholder="New label"
                                       value="{{ $override?->override_type === 'relabel' ? $override->override_label : '' }}"
                                       class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 w-full">
                            </div>
                            <div x-show="type === 'change_required'">
                                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="is_required" value="1"
                                           @checked($override?->override_type === 'change_required' && $override->is_required)>
                                    Required
                                </label>
                            </div>
                            <x-primary-button type="submit">Save override</x-primary-button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-400">No inherited fields.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
