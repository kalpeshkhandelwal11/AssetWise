<x-app-layout>
    @section('page-title', 'Depreciation Schedule')

    <div class="max-w-4xl">
        <x-breadcrumb :items="[
            ['label' => $asset->name, 'url' => route('assets.show', $asset)],
            ['label' => 'Depreciation', 'url' => route('assets.depreciation.edit', $asset)],
            ['label' => 'Schedule'],
        ]" class="mb-4" />

        @if(! $setting || $lines->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No depreciation schedule exists for this asset yet.
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Period</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Days</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Depreciation</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Accumulated</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Book Value</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($lines as $line)
                            <tr>
                                <td class="px-4 py-2.5 text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::create($line->period_year, $line->period_month, 1)->format('M Y') }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-500">{{ $line->days_in_period }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-900 dark:text-gray-100">{{ number_format($line->depreciation_amount, 2) }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-500">{{ number_format($line->accumulated_depreciation, 2) }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-900 dark:text-gray-100">{{ number_format($line->closing_book_value, 2) }}</td>
                                <td class="px-4 py-2.5">
                                    <x-status-badge :color="$line->status === 'posted' ? 'green' : 'gray'" :label="ucfirst($line->status)" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
