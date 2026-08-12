{{--
    M15 — the service worker's navigation fallback.

    Deliberately NOT x-app-layout: that dereferences auth()->user() and pulls in the whole
    sidebar, neither of which is safe here (the page is served to logged-out users too, and
    is precached at install time when there is no session at all).

    @vite gives it the real stylesheet, which is precached so it styles correctly offline;
    the inline block below keeps it legible if that asset ever misses.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('pwa.theme_color') }}">
    <title>Offline — {{ config('app.name', 'AssetWise') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    @vite(['resources/css/app.css'])
    <style>
        /* Fallback only — applies if the precached stylesheet is unavailable. */
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .pwa-offline { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; text-align: center; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-950 font-sans antialiased">
<script>
    // Same pre-paint dark-mode flip as app.js, inlined because app.js is not loaded here.
    (function () {
        var stored = localStorage.getItem('theme');
        if (stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>

<div class="pwa-offline">
    <div class="max-w-sm">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-indigo-600 flex items-center justify-center mb-6">
            <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
            </svg>
        </div>

        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">You're offline</h1>

        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
            {{ config('app.name', 'AssetWise') }} needs a connection to load this page. Your work is safe —
            nothing was lost. Reconnect and try again.
        </p>

        <button type="button" onclick="location.reload()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Try again
        </button>

        <p class="mt-8 text-xs text-gray-400">
            Creating and editing records requires connectivity.
        </p>
    </div>
</div>
</body>
</html>
