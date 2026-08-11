<x-data-table :paginator="$rows">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transition</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Changed By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reason</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($rows as $history)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="{{ route('assets.show', $history->asset) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $history->asset?->name ?? 'Asset #'.$history->asset_id }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $history->asset?->company?->name }}</td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                        {{ $history->fromStatus?->name ?? '—' }} → {{ $history->toStatus?->name ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $history->changedBy?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $history->reason ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $history->created_at->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">No status transitions found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
