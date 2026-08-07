@props(['status'])

@php
$styles = [
    'pending'  => ['bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'bg-amber-500'],
    'approved' => ['bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', 'bg-green-500'],
    'rejected' => ['bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', 'bg-red-500'],
][$status] ?? ['bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400', 'bg-gray-400'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {$styles[0]}"]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $styles[1] }}"></span> {{ ucfirst($status) }}
</span>
