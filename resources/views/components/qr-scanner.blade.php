@props([
    // Heading shown above the viewfinder; pass null on screens that already have their own.
    'heading' => 'Scan a tag',
    'hint'    => 'Point the camera at the QR code or barcode on the asset label.',
])

@php
    // html5-qrcode mounts by element id, so two instances on one page must not collide.
    $viewerId = 'qr-viewer-' . Str::random(8);

    // route() cannot build a URL with a blank parameter, so we hand the component a template
    // with a placeholder and let it substitute the decoded tag number client-side.
    $tagPlaceholder = '__ASSETWISE_TAG__';
@endphp

{{--
    M15 — one scanner, two consumers: the standalone /scan page and M10's verify worklist.

    Both navigate to the same scan.resolve route because M10 already taught ScanController
    to redirect an audit.verify holder with a pending item into the verification screen. So
    there is no second code path here and no per-consumer special-casing.

    CLAUDE.md gotcha: the x-data expression contains commas, so it must be @js(...) — @json
    would swallow everything after the first comma as its flags argument and emit raw quotes
    that terminate the attribute.
--}}
<div
    x-data="qrScanner(@js([
        'viewerId'       => $viewerId,
        'resolveUrl'     => route('scan.resolve', $tagPlaceholder),
        'tagPlaceholder' => $tagPlaceholder,
    ]))"
    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 sm:p-5">

    @if($heading)
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ $heading }}</h2>
    @endif
    @if($hint)
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ $hint }}</p>
    @endif

    {{-- Viewfinder — html5-qrcode injects the <video> here once started. --}}
    <div x-show="active" x-cloak class="mb-3">
        <div id="{{ $viewerId }}" class="w-full max-w-sm mx-auto overflow-hidden rounded-lg bg-black"></div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" @click="start()" x-show="!active" :disabled="starting"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span x-text="starting ? 'Starting camera…' : 'Start camera'"></span>
        </button>

        <button type="button" @click="stop()" x-show="active" x-cloak
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            Stop camera
        </button>
    </div>

    <p x-show="error" x-cloak x-text="error"
       class="mt-3 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2"></p>

    {{-- Manual entry — UF-18 step 4's required fallback. Plain server-side POST so it keeps
         working with no camera, no permission, and no JavaScript at all. --}}
    <form method="POST" action="{{ route('scan.lookup') }}" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
        @csrf
        <label for="{{ $viewerId }}-manual" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
            Or enter the tag number
        </label>
        <div class="flex gap-2">
            <input id="{{ $viewerId }}-manual" type="text" name="tag_number" required autocomplete="off"
                   placeholder="e.g. AW-000123"
                   class="flex-1 min-w-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            <button type="submit"
                    class="flex-shrink-0 px-4 py-2 rounded-lg bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 dark:hover:bg-gray-600 text-white text-sm font-medium transition-colors">
                Go
            </button>
        </div>
        @error('tag_number')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </form>
</div>
