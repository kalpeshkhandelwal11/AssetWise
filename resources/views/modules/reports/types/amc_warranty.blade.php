<x-data-table :paginator="$rows">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kind</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Provider / Vendor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Coverage Window</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($rows as $contract)
                @php
                    $expiryStatus = \App\Services\ReportService::expiryStatus($contract->end_date);
                    $expiryColor = match($expiryStatus) {
                        'expired'  => 'red',
                        'expiring' => 'amber',
                        'active'   => 'green',
                        default    => 'gray',
                    };
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="{{ route('assets.show', $contract->asset_id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $contract->asset?->name ?? 'Asset #'.$contract->asset_id }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $contract->asset?->company?->name }}</td>
                    <td class="px-4 py-3">
                        <x-status-badge :color="$contract->kind === 'amc' ? 'indigo' : 'blue'" :label="ucfirst($contract->kind)" />
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $contract->provider_name }}</td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        {{ optional($contract->start_date)->format('d M Y') ?? '—' }} – {{ optional($contract->end_date)->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <x-status-badge :color="$expiryColor" :label="ucfirst($expiryStatus)" />
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $contract->cost ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No AMC or warranty records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
