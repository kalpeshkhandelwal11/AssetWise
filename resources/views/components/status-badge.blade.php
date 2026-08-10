@props(['color' => 'gray', 'label' => null, 'dot' => true])

@php
$badgeStyles = [
    'green'  => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    'red'    => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'amber'  => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    'blue'   => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'gray'   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
];
$dotStyles = [
    'green' => 'bg-green-500', 'red' => 'bg-red-500', 'amber' => 'bg-amber-500',
    'indigo' => 'bg-indigo-500', 'blue' => 'bg-blue-500', 'gray' => 'bg-gray-400',
];
$badgeClass = $badgeStyles[$color] ?? $badgeStyles['gray'];
$dotClass = $dotStyles[$color] ?? $dotStyles['gray'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {$badgeClass}"]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
    @endif
    {{ $label ?? $slot }}
</span>
