<x-data-table :paginator="$rows">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Performed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vendor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Logged By</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($rows as $record)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="{{ route('assets.show', $record->asset_id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $record->asset?->name ?? 'Asset #'.$record->asset_id }}
                        </a>
                        @if($record->is_capitalized)
                            <x-status-badge color="indigo" label="Capitalized" class="ml-1" />
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $record->asset?->company?->name }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $record->maintenanceType?->name }}</td>
                    <td class="px-4 py-3">
                        @php
                            $statusColor = match($record->status) {
                                'completed'   => 'green',
                                'in_progress' => 'blue',
                                'cancelled'   => 'red',
                                default       => 'amber',
                            };
                        @endphp
                        <x-status-badge :color="$statusColor" :label="ucwords(str_replace('_', ' ', $record->status))" />
                    </td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ optional($record->performed_date)->format('d M Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $record->vendor ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $record->cost ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $record->loggedBy?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">No maintenance records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
