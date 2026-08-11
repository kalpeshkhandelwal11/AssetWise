<x-data-table :paginator="$rows">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Custodian</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Custom Fields</th>
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
                    <td class="px-4 py-3"><x-status-badge :label="$asset->status?->name" /></td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $asset->custodian?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                        @forelse($asset->fieldValues->filter(fn ($fv) => $fv->categoryField) as $fieldValue)
                            <div>{{ $fieldValue->categoryField->label }}: {{ $fieldValue->rawValue() }}</div>
                        @empty
                            —
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">No assets found.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
