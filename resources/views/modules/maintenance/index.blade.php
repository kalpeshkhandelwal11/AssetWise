<x-app-layout>
    @section('page-title', 'Maintenance')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Maintenance</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Preventive and corrective maintenance records across every asset.</p>
    </div>

    <x-filter-bar :clear="route('maintenance.index')">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search asset name or tag…"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
        <select name="maintenance_type_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Types</option>
            @foreach($maintenanceTypes as $type)
                <option value="{{ $type->id }}" @selected(request('maintenance_type_id') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            @foreach(['scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-data-table :paginator="$records">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vendor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Logged By</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($records as $record)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('assets.show', $record->asset) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $record->asset?->name ?? 'Asset #'.$record->asset_id }}
                            </a>
                            <div class="text-xs text-gray-400">{{ $record->asset?->company?->name }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $record->maintenanceType?->name }}</td>
                        <td class="px-4 py-3">
                            @php
                                $statusColor = match($record->status) {
                                    'completed' => 'green', 'in_progress' => 'amber', 'cancelled' => 'red', default => 'gray',
                                };
                            @endphp
                            <x-status-badge :color="$statusColor" :label="ucwords(str_replace('_', ' ', $record->status))" />
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $record->vendor ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $record->cost !== null ? number_format($record->cost, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $record->loggedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $record->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No maintenance records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-layout>
