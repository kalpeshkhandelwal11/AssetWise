@props(['clear' => null])

<form method="GET" {{ $attributes->merge(['class' => 'flex flex-wrap gap-3 mb-5']) }}>
    {{ $slot }}

    <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Filter</button>
    @if($clear)
        <a href="{{ $clear }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">Clear</a>
    @endif
</form>
