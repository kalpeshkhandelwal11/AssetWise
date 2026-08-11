<x-app-layout>
    @section('page-title', $record->exists ? 'Edit Maintenance Record' : 'Log Maintenance')

    <div class="max-w-2xl">
        <x-breadcrumb :items="[
            ['label' => $asset->name, 'url' => route('assets.show', $asset)],
            ['label' => $record->exists ? 'Edit Maintenance Record' : 'Log Maintenance'],
        ]" class="mb-4" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <form method="POST" action="{{ $record->exists ? route('assets.maintenance.update', [$asset, $record]) : route('assets.maintenance.store', $asset) }}" class="space-y-5">
                @csrf
                @if($record->exists) @method('PUT') @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <x-input-label for="maintenance_type_id" value="Maintenance Type" />
                        <select id="maintenance_type_id" name="maintenance_type_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select —</option>
                            @foreach($maintenanceTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('maintenance_type_id', $record->maintenance_type_id) == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('maintenance_type_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            @foreach(['scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $record->status ?? 'scheduled') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-1" />
                        @if(! $record->exists || $record->status !== 'in_progress')
                            <p class="text-xs text-gray-400 mt-1">Setting "In Progress" flips the asset's status to "In Maintenance" until this record is completed or cancelled.</p>
                        @endif
                    </div>
                    <div>
                        <x-input-label for="scheduled_date" value="Scheduled Date" />
                        <x-text-input id="scheduled_date" type="date" name="scheduled_date" class="mt-1 block w-full" :value="old('scheduled_date', optional($record->scheduled_date)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('scheduled_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="performed_date" value="Performed Date" />
                        <x-text-input id="performed_date" type="date" name="performed_date" class="mt-1 block w-full" :value="old('performed_date', optional($record->performed_date)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('performed_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="vendor" value="Vendor" />
                        <x-text-input id="vendor" name="vendor" class="mt-1 block w-full" :value="old('vendor', $record->vendor)" />
                        <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="cost" value="Cost" />
                        <x-text-input id="cost" type="number" step="0.01" min="0" name="cost" class="mt-1 block w-full" :value="old('cost', $record->cost)" />
                        <x-input-error :messages="$errors->get('cost')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('description', $record->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <a href="{{ route('assets.show', $asset) }}"
                       class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                        Cancel
                    </a>
                    <x-primary-button>{{ $record->exists ? 'Update Record' : 'Log Maintenance' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
