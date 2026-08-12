<x-app-layout>
    @section('page-title', 'Kit Assignment History')

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Kit Assignment History</h1>
        <a href="{{ route('kits.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← Kits</a>
    </div>

    <x-data-table :paginator="$assignments">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kit / Bundle</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Direction</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Assets</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Mode</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($assignments as $assignment)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('kit-assignments.show', $assignment) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $assignment->kit?->name ?? 'Ad-hoc bundle' }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ ucfirst($assignment->direction) }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $assignment->movementType?->name }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $assignment->movements_count }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $assignment->approval_mode === 'per_asset' ? 'Per asset' : 'Single' }}</td>
                        <td class="px-4 py-3">
                            @php $c = match($assignment->status) { 'completed' => 'green', 'rejected' => 'red', default => 'amber' }; @endphp
                            <x-status-badge :color="$c" :label="ucwords(str_replace('_', ' ', $assignment->status))" />
                        </td>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $assignment->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No kit assignments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-layout>
