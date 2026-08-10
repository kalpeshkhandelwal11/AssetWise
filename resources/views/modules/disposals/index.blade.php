<x-app-layout>
    @section('page-title', 'Disposal & Scrap')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Disposal & Scrap</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Disposal requests, write-off and scrap completion</p>
        </div>
        @can('disposal.request')
        <a href="{{ route('disposals.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Disposal Request
        </a>
        @endcan
    </div>

    <x-filter-bar :clear="route('disposals.index')">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search asset name or tag…"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
        <select name="disposal_type_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Types</option>
            @foreach($disposalTypes as $type)
                <option value="{{ $type->id }}" @selected(request('disposal_type_id') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            @foreach(['pending_approval' => 'Pending Approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'written_off' => 'Written Off', 'scrapped' => 'Scrapped'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-data-table :paginator="$disposals">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Requested By</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($disposals as $disposal)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('disposals.show', $disposal) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $disposal->asset?->name ?? 'Asset #' . $disposal->asset_id }}
                            </a>
                        </td>
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
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $disposal->requestedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $disposal->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">No disposal requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-layout>
