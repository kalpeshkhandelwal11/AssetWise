<x-app-layout>
    @section('page-title', 'Dashboard')

    <x-filter-bar :clear="route('dashboard')" class="mb-4">
        <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Companies</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" @selected($selectedCompany == $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    {{-- KPI Stats row 1 --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card label="Total Assets" :value="$totalAssets" color="indigo"
            :href="route('assets.index')"
            :icon="'<svg class=\'w-6 h-6\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10\'/></svg>'" />
        <x-stat-card label="Assigned Assets" :value="$assignedAssets" color="green"
            :href="route('assets.index', ['status' => 'ASSIGNED'])"
            :icon="'<svg class=\'w-6 h-6\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z\'/></svg>'" />
        <x-stat-card label="In Maintenance" :value="$inMaintenance" color="amber"
            :href="route('assets.index', ['status' => 'MAINTENANCE'])"
            :icon="'<svg class=\'w-6 h-6\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z\'/><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M15 12a3 3 0 11-6 0 3 3 0 016 0z\'/></svg>'" />
        <x-stat-card label="Pending Approvals" :value="$pendingCount" color="red"
            :href="route('approvals.index')"
            :icon="'<svg class=\'w-6 h-6\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z\'/></svg>'" />
    </div>

    {{-- KPI Stats row 2 --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <x-stat-card label="Expiring Warranties (30d)" :value="$warrantyExpiring" color="purple"
            :href="route('assets.index')"
            :icon="'<svg class=\'w-6 h-6\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z\'/></svg>'" />
        <x-stat-card label="Available Tags" :value="$availableTags" color="green"
            :href="route('admin.tags.index')"
            :icon="'<svg class=\'w-6 h-6\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0a8 8 0 11-16 0 8 8 0 0116 0z\'/></svg>'" />
        <x-stat-card label="Disposed This Year" :value="$disposedYear" color="red"
            :href="route('disposals.index')"
            :icon="'<svg class=\'w-6 h-6\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16\'/></svg>'" />
        <x-stat-card label="AMC Expiring (30d)" :value="$amcExpiring" color="blue"
            :href="route('assets.index')"
            :icon="'<svg class=\'w-6 h-6\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4\'/></svg>'" />
    </div>

    {{-- Main content --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Recent Movements --}}
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100">Recent Movements</h2>
                <a href="{{ route('movements.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">View all</a>
            </div>

            @if ($recentMovements->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-center p-5">
                    <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No movements recorded yet</p>
                    <a href="{{ route('movements.create') }}" class="mt-3 text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 font-medium">Create first movement →</a>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($recentMovements as $movement)
                        <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ $movement->asset?->name ?? 'Asset #'.$movement->asset_id }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $movement->movementType?->name ?? '—' }}
                                    @if ($movement->requestedBy)
                                        · by {{ $movement->requestedBy->name }}
                                    @endif
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span @class([
                                    'inline-flex text-xs px-2 py-0.5 rounded-full font-medium',
                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' => $movement->status === 'pending_approval',
                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'  => $movement->status === 'applied',
                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'          => $movement->status === 'rejected',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'         => !in_array($movement->status, ['pending_approval','applied','rejected']),
                                ])>{{ ucfirst(str_replace('_', ' ', $movement->status)) }}</span>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $movement->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Right column --}}
        <div class="space-y-4">

            {{-- Pending Your Approval --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Pending Your Approval</h2>
                    <span class="text-xs rounded-full px-2 py-0.5 font-medium {{ $pendingApprovals->count() ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                        {{ $pendingApprovals->count() }}
                    </span>
                </div>
                <div class="p-4">
                    @if ($pendingApprovals->isEmpty())
                        <p class="text-xs text-gray-400 dark:text-gray-500 text-center py-4">No items pending your approval</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($pendingApprovals as $approval)
                                <a href="{{ route('approvals.show', $approval) }}"
                                   class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors group">
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                            {{ $approval->approvable_label }}
                                        </p>
                                        <p class="text-xs text-gray-400">Step {{ $approval->current_step }} · {{ $approval->created_at->diffForHumans() }}</p>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-300 dark:text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                        <a href="{{ route('approvals.index') }}" class="block text-center text-xs text-indigo-600 dark:text-indigo-400 hover:underline mt-3">View all</a>
                    @endif
                </div>
            </div>

            {{-- Expiry Alerts --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Expiry Alerts <span class="font-normal text-gray-400 text-xs">(30 days)</span></h2>
                    <span class="text-xs rounded-full px-2 py-0.5 font-medium {{ $expiryAlerts->count() ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                        {{ $warrantyExpiring + $amcExpiring }}
                    </span>
                </div>
                <div class="p-4">
                    @if ($expiryAlerts->isEmpty())
                        <p class="text-xs text-gray-400 dark:text-gray-500 text-center py-4">No upcoming expiries</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($expiryAlerts as $asset)
                                <a href="{{ route('assets.show', $asset) }}"
                                   class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors group">
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                            {{ $asset->name }}
                                        </p>
                                        @if ($asset->warranty_expiry && $asset->warranty_expiry->isFuture() && $asset->warranty_expiry->diffInDays() <= 30)
                                            <p class="text-xs text-amber-600 dark:text-amber-400">Warranty: {{ $asset->warranty_expiry->format('d M Y') }}</p>
                                        @elseif ($asset->amc_expiry && $asset->amc_expiry->isFuture() && $asset->amc_expiry->diffInDays() <= 30)
                                            <p class="text-xs text-amber-600 dark:text-amber-400">AMC: {{ $asset->amc_expiry->format('d M Y') }}</p>
                                        @endif
                                    </div>
                                    <svg class="w-4 h-4 text-gray-300 dark:text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 space-y-1">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-3">Quick Actions</h2>
                <a href="{{ route('assets.create') }}" class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add New Asset
                </a>
                <a href="{{ route('movements.create') }}" class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Create Movement
                </a>
                <a href="{{ route('admin.tags.index') }}" class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0a8 8 0 11-16 0 8 8 0 0116 0z"/></svg>
                    Manage Tags
                </a>
                <a href="{{ route('assets.import.index') }}" class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/></svg>
                    Bulk Import
                </a>
                <a href="{{ route('disposals.create') }}" class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    New Disposal Request
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
