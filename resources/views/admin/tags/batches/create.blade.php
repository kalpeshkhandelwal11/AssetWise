<x-app-layout>
    @section('page-title', 'Generate Tag Batch')

    <div class="max-w-lg">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.tags.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Generate Tag Batch</h1>
        </div>

        <form method="POST" action="{{ route('admin.tags.batches.store') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf

            <div>
                <x-input-label for="quantity" value="Quantity *" />
                <x-text-input id="quantity" type="number" min="1" max="1000" name="quantity" class="mt-1 block w-full" :value="old('quantity', 50)" required autofocus />
                <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                All generated tags will be <span class="font-medium">{{ ucfirst($codeType) }}</span> codes,
                per the current
                @can('settings.manage')
                    <a href="{{ route('admin.settings.tags.edit') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">global tag setting</a>.
                @else
                    global tag setting.
                @endcan
                Tag numbers are assigned sequentially and are never reused.
            </p>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="{{ route('admin.tags.index') }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                    Cancel
                </a>
                <x-primary-button>Generate</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
