@php
    $statusColor = match($campaign->status) {
        'active' => 'green', 'closed' => 'gray', default => 'amber',
    };
@endphp
<x-app-layout>
    @section('page-title', $campaign->name)

    <x-breadcrumb :items="[
        ['label' => 'Audit Campaigns', 'url' => route('audits.campaigns.index')],
        ['label' => $campaign->name],
    ]" class="mb-4" />

    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $campaign->name }}</h1>
                <x-status-badge :color="$statusColor" :label="ucwords($campaign->status)" />
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $campaign->auditType?->name }} · started {{ $campaign->start_date->format('d M Y') }}</p>
        </div>
        <div class="flex gap-2">
            @if($campaign->isDraft())
                <a href="{{ route('audits.campaigns.edit', $campaign) }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Edit
                </a>
                <x-confirm-modal :action="route('audits.campaigns.activate', $campaign)"
                                  method="POST"
                                  title="Activate this campaign?"
                                  message="Assets matching the scope filters will be snapshotted into audit items and assigned auditors will be notified."
                                  confirm-label="Activate"
                                  trigger-class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Activate Campaign
                </x-confirm-modal>
            @elseif($campaign->isActive())
                <a href="{{ route('audits.verify', ['campaign' => $campaign->id]) }}"
                   class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Open Verify Screen
                </a>
                <x-confirm-modal :action="route('audits.campaigns.close', $campaign)"
                                  method="POST"
                                  title="Close this campaign?"
                                  message="Verification will be locked. This cannot be undone."
                                  confirm-label="Close Campaign"
                                  trigger-class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Close Campaign
                </x-confirm-modal>
            @else
                <a href="{{ route('audits.campaigns.report', $campaign) }}"
                   class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Compliance Report
                </a>
            @endif
        </div>
    </div>

    @if($campaign->isDraft())
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 mb-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $matchingCount }}</span>
                assets currently match this campaign's scope filters.
            </p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
            <x-stat-card label="Total" :value="$progress['total']" color="indigo" icon='<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>' />
            <x-stat-card label="Pending" :value="$progress['pending']" color="blue" icon='<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' />
            <x-stat-card label="Verified" :value="$progress['verified']" color="green" icon='<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' />
            <x-stat-card label="Missing" :value="$progress['missing']" color="red" icon='<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' />
            <x-stat-card label="Damaged" :value="$progress['damaged']" color="amber" icon='<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.75-2.97l-6.93-12a2 2 0 00-3.5 0l-6.93 12A2 2 0 005.07 19z"/></svg>' />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 mb-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Verification progress</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $progress['verified_pct'] }}%</p>
            </div>
            <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-indigo-600 rounded-full" style="width: {{ $progress['verified_pct'] }}%"></div>
            </div>
        </div>
    @endif

    <x-data-table :paginator="$items">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Verified By</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Verified At</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($items as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('assets.show', $item->asset_id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $item->asset?->name ?? 'Asset #'.$item->asset_id }}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $itemColor = match($item->status) {
                                    'verified' => 'green', 'missing' => 'red', 'damaged' => 'amber', default => 'gray',
                                };
                            @endphp
                            <x-status-badge :color="$itemColor" :label="ucwords($item->status)" />
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $item->verifiedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ optional($item->verified_at)->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-xs truncate">{{ $item->notes ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">No audit items yet — activate the campaign to generate them.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-layout>
