@props(['label', 'active' => false])

@php($panelId = 'nav-group-' . \Illuminate\Support\Str::slug($label))

{{-- Collapsible sidebar section (M07-style extracted component). `open` is local Alpine
     state; `sidebarCollapsed`/`sidebarOpen` are inherited from the x-data on <body> in
     layouts/app.blade.php — Blade components don't create a new Alpine scope, they just
     insert markup, so the parent state is still reachable here. --}}
<div x-data="{ open: {{ $active ? 'true' : 'false' }} }" @keydown.escape="open = false" class="relative">
    <button type="button"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-controls="{{ $panelId }}"
            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 ease-out
                   focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900"
            :class="open
                ? 'text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-800/60 ring-1 ring-gray-200 dark:ring-gray-700/50'
                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-white'">
        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center" aria-hidden="true">{{ $icon ?? '' }}</span>
        <span x-show="!sidebarCollapsed" x-transition.opacity.duration.150ms class="flex-1 truncate text-left">{{ $label }}</span>
        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="w-4 h-4 flex-shrink-0 transition-transform duration-200 ease-out" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div id="{{ $panelId }}"
         x-show="open && !sidebarCollapsed"
         x-collapse.duration.200ms
         role="group"
         aria-label="{{ $label }}"
         class="mt-1 ml-4 pl-4 space-y-1 border-l-2 border-gray-200 dark:border-gray-700/60">
        {{ $slot }}
    </div>
</div>
