<x-data-table :paginator="$rows">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">From → To Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Requested By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($rows as $movement)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="{{ route('assets.show', $movement->asset) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $movement->asset?->name ?? 'Asset #'.$movement->asset_id }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $movement->movementType?->name }}</td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                        {{ $movement->fromCompany?->name ?? '—' }} → {{ $movement->toCompany?->name ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $statusColor = match($movement->status) {
                                'completed' => 'green', 'rejected' => 'red', default => 'amber',
                            };
                        @endphp
                        <x-status-badge :color="$statusColor" :label="ucfirst(str_replace('_', ' ', $movement->status))" />
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $movement->requestedBy?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $movement->created_at->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">No movements found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
