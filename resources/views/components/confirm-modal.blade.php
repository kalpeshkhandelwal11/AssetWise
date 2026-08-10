@props([
    'action',
    'method' => 'DELETE',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'confirmLabel' => 'Delete',
    'triggerClass' => '',
    'triggerTitle' => null,
])

<div x-data="{ open: false }" class="inline">
    <button type="button" @click="open = true" title="{{ $triggerTitle }}" class="{{ $triggerClass }}">
        {{ $slot }}
    </button>

    <div x-show="open" x-transition.opacity x-cloak @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4" style="display: none;">
        <div @click.outside="open = false" class="w-full max-w-sm bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $message }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="open = false"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Cancel
                </button>
                <form method="POST" action="{{ $action }}">
                    @csrf
                    @if(strtoupper($method) !== 'POST')
                        @method($method)
                    @endif
                    <button type="submit" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        {{ $confirmLabel }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
