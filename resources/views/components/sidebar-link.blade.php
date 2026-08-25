@props(['href', 'active' => false, 'child' => false])

<a href="{{ $href }}"
   @if($active) aria-current="page" @endif
   {{ $attributes->class([
        'group relative flex items-center gap-3 text-sm font-medium transition-all duration-200 ease-out',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900',
        'px-3 py-2.5 rounded-xl' => ! $child,
        'px-3 py-2 rounded-lg' => $child,
        'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20' => $active,
        'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800/70 hover:text-gray-900 dark:hover:text-white' => ! $active,
   ]) }}>
    @isset($icon)
        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center" aria-hidden="true">{{ $icon }}</span>
    @endisset
    <span x-show="!sidebarCollapsed" x-transition.opacity.duration.150ms class="flex-1 min-w-0 truncate text-left">{{ $slot }}</span>
    @isset($badge)
        <span x-show="!sidebarCollapsed" x-transition.opacity.duration.150ms>{{ $badge }}</span>
    @endisset
</a>
