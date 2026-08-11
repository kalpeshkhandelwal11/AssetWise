<x-data-table>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assigned</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Available</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">In Maintenance</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Utilization</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($rows as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['company']->name }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['total'] }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['assigned'] }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['available'] }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['in_maintenance'] }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-24 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full bg-indigo-600" style="width: {{ min(100, $row['utilization_pct']) }}%"></div>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['utilization_pct'] }}%</span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">No companies found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
