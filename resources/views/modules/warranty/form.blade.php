<x-app-layout>
    @section('page-title', $record->exists ? 'Edit Warranty Record' : 'Add Warranty Record')

    <div class="max-w-2xl">
        <x-breadcrumb :items="[
            ['label' => $asset->name, 'url' => route('assets.show', $asset)],
            ['label' => $record->exists ? 'Edit Warranty Record' : 'Add Warranty Record'],
        ]" class="mb-4" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <form method="POST" action="{{ $record->exists ? route('assets.warranty.update', [$asset, $record]) : route('assets.warranty.store', $asset) }}" class="space-y-5">
                @csrf
                @if($record->exists) @method('PUT') @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <x-input-label for="provider" value="Provider" />
                        <x-text-input id="provider" name="provider" required class="mt-1 block w-full" :value="old('provider', $record->provider)" />
                        <x-input-error :messages="$errors->get('provider')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="start_date" value="Start Date" />
                        <x-text-input id="start_date" type="date" name="start_date" class="mt-1 block w-full" :value="old('start_date', optional($record->start_date)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="End Date" />
                        <x-text-input id="end_date" type="date" name="end_date" required class="mt-1 block w-full" :value="old('end_date', optional($record->end_date)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="terms" value="Terms" />
                    <textarea id="terms" name="terms" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('terms', $record->terms) }}</textarea>
                    <x-input-error :messages="$errors->get('terms')" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <a href="{{ route('assets.show', $asset) }}"
                       class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                        Cancel
                    </a>
                    <x-primary-button>{{ $record->exists ? 'Update Record' : 'Add Record' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
