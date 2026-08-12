<x-app-layout>
    @section('page-title', 'Kit Settings')

    <div class="max-w-lg">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">Kit Settings</h1>

        <form method="POST" action="{{ route('admin.settings.kits.update') }}"
              class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            @csrf
            @method('PATCH')

            <div>
                <x-input-label value="Kit Assignment Approval Mode *" />
                <p class="text-xs text-gray-400 mt-0.5 mb-2">
                    How approvals are raised when a kit or bundle is assigned.
                </p>
                <div class="space-y-2">
                    <label class="flex items-start gap-2">
                        <input type="radio" name="kit_assignment_approval_mode" value="single" @checked(old('kit_assignment_approval_mode', $mode) === 'single')
                               class="mt-1 rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300"><strong>Single</strong> — one approval for the whole kit; one rejection cancels the entire bundle.</span>
                    </label>
                    <label class="flex items-start gap-2">
                        <input type="radio" name="kit_assignment_approval_mode" value="per_asset" @checked(old('kit_assignment_approval_mode', $mode) === 'per_asset')
                               class="mt-1 rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300"><strong>Per asset</strong> — each asset in the kit is approved independently via the transfer workflow.</span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('kit_assignment_approval_mode')" class="mt-1" />
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <x-primary-button>Save</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
