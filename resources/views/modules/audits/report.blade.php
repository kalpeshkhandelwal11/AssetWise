<x-app-layout>
    @section('page-title', 'Compliance Report')

    <x-breadcrumb :items="[
        ['label' => 'Audit Campaigns', 'url' => route('audits.campaigns.index')],
        ['label' => $campaign->name, 'url' => route('audits.campaigns.show', $campaign)],
        ['label' => 'Compliance Report'],
    ]" class="mb-4" />

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $campaign->name }} — Compliance Report</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $campaign->auditType?->name }}</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('audits.campaigns.report.export', $campaign) }}">
                @csrf
                <input type="hidden" name="format" value="xlsx">
                <button type="submit" class="px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">Export Excel</button>
            </form>
            <form method="POST" action="{{ route('audits.campaigns.report.export', $campaign) }}">
                @csrf
                <input type="hidden" name="format" value="pdf">
                <button type="submit" class="px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">Export PDF</button>
            </form>
        </div>
    </div>

    <x-data-table>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expected Location</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expected Custodian</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Verified By</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Verified At</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('assets.show', $row->asset_id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $row->asset?->name ?? 'Asset #'.$row->asset_id }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $row->asset?->company?->name }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $row->expectedLocation?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $row->expectedCustodian?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $rowColor = match($row->status) {
                                    'verified' => 'green', 'missing' => 'red', 'damaged' => 'amber', default => 'gray',
                                };
                            @endphp
                            <x-status-badge :color="$rowColor" :label="ucwords($row->status)" />
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $row->verifiedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ optional($row->verified_at)->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-xs truncate">{{ $row->notes ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">No audit items for this campaign.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-layout>
