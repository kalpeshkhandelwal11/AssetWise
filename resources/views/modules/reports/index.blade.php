<x-app-layout>
    @section('page-title', 'Reports')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Reports</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Filterable, exportable reports across every module.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($reportTypes as $key => $report)
            @if($report['enabled'])
                <a href="{{ route('reports.show', $key) }}"
                   class="block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors">
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ $report['label'] }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $report['description'] }}</p>
                </a>
            @else
                <div class="block bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 p-5 opacity-50 cursor-not-allowed">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ $report['label'] }}</h2>
                        <x-status-badge color="gray" label="Coming soon" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $report['description'] }}</p>
                </div>
            @endif
        @endforeach
    </div>
</x-app-layout>
