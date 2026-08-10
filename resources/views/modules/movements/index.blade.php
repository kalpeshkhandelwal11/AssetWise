<x-app-layout>
    @section('page-title', 'Movement History')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Movement History</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Assignment, transfer, return and custodian-change requests across all assets</p>
        </div>
        @canany(['movement.assign', 'movement.transfer'])
        <a href="{{ route('movements.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Movement
        </a>
        @endcanany
    </div>

    <x-filter-bar :clear="route('movements.index')">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search asset name or tag…"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
        <select name="movement_type_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Types</option>
            @foreach($movementTypes as $type)
                <option value="{{ $type->id }}" @selected(request('movement_type_id') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="pending_approval" @selected(request('status') === 'pending_approval')>Pending Approval</option>
            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
            <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
        </select>
    </x-filter-bar>

    <x-data-table :paginator="$movements">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Destination</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Requested By</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($movements as $movement)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('assets.show', $movement->asset_id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $movement->asset?->name ?? 'Asset #' . $movement->asset_id }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                            {{ $movement->movementType?->name }}
                            @if($movement->batch_id)
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400"
                                      title="Part of a bulk movement — one approval covers the whole batch">Bulk</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 text-xs">
                            {{ collect([$movement->toCompany?->name, $movement->toLocation?->name, $movement->toCustodian?->name])->filter()->join(' · ') ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $statusColor = match($movement->status) {
                                    'completed' => 'green',
                                    'rejected'  => 'red',
                                    default     => 'amber',
                                };
                            @endphp
                            <x-status-badge :color="$statusColor" :label="ucwords(str_replace('_', ' ', $movement->status))" />
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $movement->requestedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $movement->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('movement.verify')
                                @if($movement->status === 'completed' && ! $movement->verified_at)
                                    <form method="POST" action="{{ route('movements.verify', $movement) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Verify</button>
                                    </form>
                                @elseif($movement->verified_at)
                                    <span class="text-xs text-gray-400">Verified</span>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No movements found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-layout>
