<x-app-layout>
    @section('page-title', 'Asset Naming')

    <div class="max-w-lg">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1">Asset Naming Series</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Auto-generates the Asset ID (<span class="font-mono">{{ $prefix }}-{{ str_pad((string) $next, max($padding,1), '0', STR_PAD_LEFT) }}</span>) when a new asset is created.</p>

        <form method="POST" action="{{ route('admin.settings.asset-naming.update') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @method('PATCH')

            <label class="flex items-center gap-2">
                <input type="hidden" name="asset_naming_enabled" value="0">
                <input type="checkbox" name="asset_naming_enabled" value="1" @checked(old('asset_naming_enabled', $enabled))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700 dark:text-gray-300">Auto-generate Asset IDs on create</span>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="asset_naming_prefix" value="Prefix" />
                    <x-text-input id="asset_naming_prefix" name="asset_naming_prefix" class="mt-1 block w-full font-mono uppercase" :value="old('asset_naming_prefix', $prefix)" />
                    <x-input-error :messages="$errors->get('asset_naming_prefix')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="asset_naming_padding" value="Digits" />
                    <x-text-input id="asset_naming_padding" type="number" min="1" max="10" name="asset_naming_padding" class="mt-1 block w-full" :value="old('asset_naming_padding', $padding)" />
                    <x-input-error :messages="$errors->get('asset_naming_padding')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="asset_naming_next" value="Next number" />
                    <x-text-input id="asset_naming_next" type="number" min="1" name="asset_naming_next" class="mt-1 block w-full" :value="old('asset_naming_next', $next)" />
                    <x-input-error :messages="$errors->get('asset_naming_next')" class="mt-1" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <x-primary-button>Save</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
