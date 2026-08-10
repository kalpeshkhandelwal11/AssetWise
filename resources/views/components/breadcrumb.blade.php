@props(['items' => []])

<nav class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400" aria-label="Breadcrumb">
    @foreach($items as $item)
        @if(!$loop->first)
            <svg class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        @endif
        @if(!empty($item['url']) && !$loop->last)
            <a href="{{ $item['url'] }}" class="hover:text-gray-900 dark:hover:text-gray-200 transition-colors truncate">{{ $item['label'] }}</a>
        @else
            <span class="text-gray-900 dark:text-gray-100 font-medium truncate">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
