<x-data-table :paginator="$rows">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Campaign</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Verified By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Verified At</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($rows as $item)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="{{ route('audits.campaigns.show', $item->campaign_id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $item->campaign?->name ?? 'Campaign #'.$item->campaign_id }}
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('assets.show', $item->asset_id) }}" class="text-gray-700 dark:text-gray-300 hover:underline">
                            {{ $item->asset?->name ?? 'Asset #'.$item->asset_id }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $item->asset?->company?->name }}</td>
                    <td class="px-4 py-3">
                        @php
                            $rowColor = match($item->status) {
                                'verified' => 'green', 'missing' => 'red', 'damaged' => 'amber', default => 'gray',
                            };
                        @endphp
                        <x-status-badge :color="$rowColor" :label="ucwords($item->status)" />
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $item->verifiedBy?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ optional($item->verified_at)->format('d M Y H:i') ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-xs truncate">{{ $item->notes ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No audit items found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
