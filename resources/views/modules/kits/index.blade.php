<x-app-layout>
    @section('page-title', 'Asset Kits')

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Asset Kits</h1>
        <div class="flex gap-2">
            <a href="{{ route('kit-assignments.index') }}"
               class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">Assignment History</a>
            @can('kits.assign')
                <a href="{{ route('kit-assignments.create') }}"
                   class="px-4 py-2 text-sm font-medium bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors">Assign a Kit / Bundle</a>
            @endcan
            @can('kits.manage')
                <a href="{{ route('kits.create') }}"
                   class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">New Kit Template</a>
            @endcan
        </div>
    </div>

    <x-data-table :paginator="$kits">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kit</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Slots</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Readiness</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($kits as $kit)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $kit->name }}</td>
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $kit->code }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $kit->items_count }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :color="($readiness[$kit->id] ?? false) ? 'green' : 'amber'"
                                            :label="($readiness[$kit->id] ?? false) ? 'Ready' : 'Incomplete'" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('kits.manage')
                                <a href="{{ route('kits.edit', $kit) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">No kit templates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-layout>
