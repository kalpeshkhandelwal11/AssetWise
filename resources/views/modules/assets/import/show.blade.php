<x-app-layout>
    @section('page-title', 'Import Batch #' . $batch->id)

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('assets.import.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $batch->filename }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $batch->category?->name ?? '—' }} &middot; uploaded by {{ $batch->user?->name ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ ucfirst($batch->status) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Rows</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $batch->total_rows }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Succeeded</p>
            <p class="text-lg font-semibold text-green-600 dark:text-green-400 mt-1">{{ $batch->success_count }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Failed</p>
            <p class="text-lg font-semibold text-red-600 dark:text-red-400 mt-1">{{ $batch->error_count }}</p>
        </div>
    </div>

    @if($batch->status === 'processing')
        {{-- Auto-refresh while the queued ProcessAssetImport job runs, so counts update without
             a manual reload. Requires a queue worker (php artisan queue:work); with no worker the
             job never runs and the batch stays "processing". --}}
        <div x-data x-init="setTimeout(() => window.location.reload(), 4000)"
             class="mb-6 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
            </svg>
            Still processing — this page refreshes automatically. (Needs a running queue worker.)
        </div>
    @endif

    <x-data-table :paginator="$rows">
        <x-slot:header>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Row report</p>
        </x-slot:header>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Row</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 font-mono">{{ $row->row_number }}</td>
                            <td class="px-4 py-3">
                                <x-status-badge :color="$row->status === 'success' ? 'green' : 'red'" :label="$row->status === 'success' ? 'Success' : 'Failed'" :dot="false" />
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                @if($row->status === 'success' && $row->asset)
                                    <a href="{{ route('assets.show', $row->asset) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row->asset->name }}</a>
                                @elseif($row->errors)
                                    <ul class="list-disc list-inside text-xs text-red-600 dark:text-red-400">
                                        @foreach($row->errors as $field => $messages)
                                            @foreach((array) $messages as $message)
                                                <li><span class="font-medium">{{ $field }}</span>: {{ $message }}</li>
                                            @endforeach
                                        @endforeach
                                    </ul>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-12 text-center text-sm text-gray-400">No rows processed yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
    </x-data-table>
</x-app-layout>
