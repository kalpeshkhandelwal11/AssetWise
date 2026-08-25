@props(['label' => null])

<div class="pt-5 first:pt-0">
    @if($label)
        <p x-show="!sidebarCollapsed" class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 select-none">{{ $label }}</p>
        <div x-show="sidebarCollapsed" class="mx-3 mb-2 border-t border-gray-200 dark:border-gray-700/60"></div>
    @endif
    <div class="space-y-1">
        {{ $slot }}
    </div>
</div>
