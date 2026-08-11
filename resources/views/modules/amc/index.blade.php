<x-app-layout>
    @section('page-title', 'AMC Contracts')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">AMC Contracts</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Annual maintenance contracts across every asset.</p>
    </div>

    <x-filter-bar :clear="route('amc.index')">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search asset name or tag…"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
    </x-filter-bar>

    <x-data-table :paginator="$contracts">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vendor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Coverage Period</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($contracts as $contract)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('assets.show', $contract->asset) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $contract->asset?->name ?? 'Asset #'.$contract->asset_id }}
                            </a>
                            <div class="text-xs text-gray-400">{{ $contract->asset?->company?->name }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $contract->vendor }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $contract->start_date->format('d M Y') }} &ndash; {{ $contract->end_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $contract->cost !== null ? number_format($contract->cost, 2) : '—' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :color="$contract->end_date->isPast() ? 'red' : 'green'" :label="$contract->end_date->isPast() ? 'Expired' : 'Active'" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">No AMC contracts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-layout>
