@props(['paginator' => null])

<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
    @isset($header)
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            {{ $header }}
        </div>
    @endisset

    <div class="overflow-x-auto">
        {{ $slot }}
    </div>

    @if($paginator && method_exists($paginator, 'hasPages') && $paginator->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $paginator->links() }}
        </div>
    @endif
</div>
