<x-app-layout>
    @section('page-title', 'Incoming / In Transit')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Incoming — In Transit</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Approved moves waiting to be scanned in at their destination.</p>
        </div>
        <a href="{{ route('scan.index') }}" class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Scan to receive</a>
    </div>

    <x-data-table>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    <th class="px-4 py-3 text-left">Asset</th>
                    <th class="px-4 py-3 text-left">Movement</th>
                    <th class="px-4 py-3 text-left">To</th>
                    <th class="px-4 py-3 text-left">Since</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($movements as $movement)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                            {{ \App\Models\Asset::labelFor($movement->asset) }}
                            <span class="text-xs text-gray-400 font-mono">{{ $movement->asset?->asset_tag }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                            {{ $movement->movementType?->name }}
                            @if($movement->batch_id)<x-status-badge color="indigo" label="Batch" :dot="false" />@endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ collect([$movement->toCompany?->name, $movement->toLocation?->name])->filter()->join(' · ') ?: 'Stores (return)' }}</td>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $movement->dispatched_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if($movement->batch_id)
                                <a href="{{ route('movements.batches.receive', $movement->batch_id) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Receive batch</a>
                            @else
                                <a href="{{ route('movements.receive.form', $movement) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Receive</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">Nothing in transit.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>

    <div class="mt-4">{{ $movements->links() }}</div>
</x-app-layout>
