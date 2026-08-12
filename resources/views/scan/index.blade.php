<x-app-layout>
    @section('page-title', 'Scan')

    <div class="max-w-lg">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Scan a Tag</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Scanning an assigned tag opens its asset. An unassigned tag opens the assign screen.
                If you are verifying an active audit campaign, you go straight to that item instead.
            </p>
        </div>

        <x-qr-scanner />

        <p class="mt-4 text-xs text-gray-400">
            The camera needs a secure connection — HTTPS in production, or localhost in development.
            Manual entry always works.
        </p>
    </div>
</x-app-layout>
