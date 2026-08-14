<x-app-layout>
    @section('page-title', 'Depreciation Schedule')

    <div class="max-w-4xl">
        <x-breadcrumb :items="[
            ['label' => $asset->name, 'url' => route('assets.show', $asset)],
            ['label' => 'Depreciation', 'url' => route('assets.depreciation.edit', $asset)],
            ['label' => 'Schedule'],
        ]" class="mb-4" />

        @if(! $setting || $rows->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No depreciation schedule exists for this asset yet.
            </div>
        @else
            {{-- Monthly/Yearly is a presentation rollup; the underlying calculation stays monthly. --}}
            <div class="flex items-center justify-end mb-3">
                <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-700 overflow-hidden text-sm">
                    <a href="{{ route('assets.depreciation.schedule', [$asset, 'view' => 'monthly']) }}"
                       class="px-3 py-1.5 {{ $view === 'monthly' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">Monthly</a>
                    <a href="{{ route('assets.depreciation.schedule', [$asset, 'view' => 'yearly']) }}"
                       class="px-3 py-1.5 border-l border-gray-300 dark:border-gray-700 {{ $view === 'yearly' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">Yearly</a>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $view === 'yearly' ? 'Year' : 'Period' }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Days</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Depreciation</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Accumulated</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Book Value</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($rows as $row)
                            <tr>
                                <td class="px-4 py-2.5 text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-500">{{ $row['days'] }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-900 dark:text-gray-100">{{ number_format($row['depreciation'], 2) }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-500">{{ number_format($row['accumulated'], 2) }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-900 dark:text-gray-100">{{ number_format($row['book_value'], 2) }}</td>
                                <td class="px-4 py-2.5">
                                    <x-status-badge :color="$row['status'] === 'posted' ? 'green' : ($row['status'] === 'partial' ? 'amber' : 'gray')" :label="ucfirst($row['status'])" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
