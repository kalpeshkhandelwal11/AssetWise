<x-app-layout>
    @section('page-title', 'Tag Settings')

    <div class="max-w-lg">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">Tag Settings</h1>

        <form method="POST" action="{{ route('admin.settings.tags.update') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @method('PATCH')

            <div>
                <x-input-label value="Tag Code Type *" />
                <p class="text-xs text-gray-400 mt-0.5 mb-2">
                    Controls what kind of code newly generated batches use. Changing this does not
                    relabel batches already generated.
                </p>
                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="tag_code_type" value="qr" @checked(old('tag_code_type', $codeType) === 'qr')
                               class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">QR Code</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="tag_code_type" value="barcode" @checked(old('tag_code_type', $codeType) === 'barcode')
                               class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Barcode (Code 128)</span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('tag_code_type')" class="mt-1" />
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <x-primary-button>Save</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
