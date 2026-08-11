<x-data-table :paginator="$rows">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Purchase Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Age Bucket</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($rows as $asset)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="{{ route('assets.show', $asset) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $asset->name }}</a>
                        <div class="text-xs text-gray-400">{{ $asset->asset_tag }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $asset->company?->name }}</td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $asset->category?->name }}</td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $asset->purchase_date->format('d M Y') }}</td>
                    <td class="px-4 py-3"><x-status-badge color="indigo" :label="\App\Services\ReportService::ageBucket($asset->purchase_date)" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">No assets with a purchase date found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
