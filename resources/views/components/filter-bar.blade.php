@props(['clear' => null, 'auto' => true])

{{-- $auto (default): filters apply automatically — selects/checkboxes on change, text on
     debounced typing (see resources/js/filter-bar.js), so no visible Filter button is needed.
     Pass :auto="false" on heavy forms (e.g. reports) to keep an explicit Filter button. --}}
<form method="GET"
      @if($auto) x-data="filterBar" x-init="restore()" @change="onChange($event)" @input.debounce.500ms="onInput($event)" @endif
      {{ $attributes->merge(['class' => 'flex flex-wrap gap-3 mb-5']) }}>
    {{ $slot }}

    @if($auto)
        {{-- Off-screen submit keeps Enter working and lets JS-less clients still submit. --}}
        <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Filter</button>
    @else
        <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Filter</button>
    @endif

    @if($clear)
        <a href="{{ $clear }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">Clear</a>
    @endif
</form>
