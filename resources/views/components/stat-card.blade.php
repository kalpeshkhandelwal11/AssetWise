@props(['label', 'value', 'icon', 'color' => 'indigo', 'trend' => null, 'trendUp' => null, 'href' => null])

@php
    $colors = [
        'indigo' => 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400',
        'green'  => 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400',
        'amber'  => 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400',
        'red'    => 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400',
        'blue'   => 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400',
        'purple' => 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
    ];
    $iconBg = $colors[$color] ?? $colors['indigo'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm hover:shadow-md transition-shadow {{ $href ? 'cursor-pointer group' : '' }}">
    <div class="flex-shrink-0 w-12 h-12 rounded-xl {{ $iconBg }} flex items-center justify-center">
        {!! $icon !!}
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $label }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ $value }}</p>
        @if($trend)
            <p class="text-xs mt-0.5 {{ $trendUp ? 'text-green-600' : 'text-red-500' }}">
                {{ $trendUp ? '▲' : '▼' }} {{ $trend }}
            </p>
        @endif
    </div>
    @if($href)
        <svg class="w-4 h-4 text-gray-300 group-hover:text-gray-500 flex-shrink-0 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    @endif
</{{ $tag }}>
