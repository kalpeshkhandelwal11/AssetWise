<x-data-table :paginator="$rows">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Value</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Requested By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($rows as $disposal)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="{{ route('disposals.show', $disposal) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $disposal->asset?->name ?? 'Asset #'.$disposal->asset_id }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $disposal->asset?->company?->name }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $disposal->disposalType?->name }}</td>
                    <td class="px-4 py-3">
                        @php
                            $statusColor = match($disposal->status) {
                                'scrapped'    => 'gray',
                                'written_off' => 'blue',
                                'approved'    => 'green',
                                'rejected'    => 'red',
                                default       => 'amber',
                            };
                        @endphp
                        <x-status-badge :color="$statusColor" :label="ucwords(str_replace('_', ' ', $disposal->status))" />
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $disposal->disposal_value ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $disposal->requestedBy?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $disposal->created_at->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No disposal requests found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
