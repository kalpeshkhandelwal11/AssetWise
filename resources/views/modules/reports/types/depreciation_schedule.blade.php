<x-data-table :paginator="$rows">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Period</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Depreciation</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Accumulated</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Book Value</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($rows as $line)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="{{ route('assets.depreciation.schedule', $line->asset_id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $line->asset?->name ?? 'Asset #'.$line->asset_id }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $line->asset?->company?->name }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::create($line->period_year, $line->period_month, 1)->format('M Y') }}</td>
                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format($line->depreciation_amount, 2) }}</td>
                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">{{ number_format($line->accumulated_depreciation, 2) }}</td>
                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format($line->closing_book_value, 2) }}</td>
                    <td class="px-4 py-3">
                        <x-status-badge :color="$line->status === 'posted' ? 'green' : 'gray'" :label="ucfirst($line->status)" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No depreciation lines found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
