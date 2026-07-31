<x-guest-layout>
    <div class="mb-6 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
            <svg class="h-7 w-7 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Set Your Password</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            You must set a new password before you can continue.
        </p>
    </div>

    <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
        Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.
    </div>

    <form method="POST" action="{{ route('password.force-change.update') }}">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <x-input-label for="password" :value="__('New Password')" />
                <x-text-input id="password" class="block mt-1 w-full" type="password"
                    name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password"
                    name="password_confirmation" required autocomplete="new-password" />
            </div>
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                {{ __('Set Password & Continue') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
