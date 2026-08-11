<x-app-layout>
    @section('page-title', 'Warranty Records')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Warranty Records</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Warranty coverage across every asset — multiple records per asset are supported.</p>
    </div>

    <x-filter-bar :clear="route('warranty.index')">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search asset name or tag…"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
    </x-filter-bar>

    <x-data-table :paginator="$records">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Provider</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Coverage Period</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
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
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $record->provider }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                            {{ optional($record->start_date)->format('d M Y') ?? '—' }} &ndash; {{ $record->end_date->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <x-status-badge :color="$record->end_date->isPast() ? 'red' : 'green'" :label="$record->end_date->isPast() ? 'Expired' : 'Active'" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-12 text-center text-sm text-gray-400">No warranty records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-layout>
