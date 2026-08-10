<x-app-layout>
    @section('page-title', 'Disposal Request')

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('disposals.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                <a href="{{ route('assets.show', $disposal->asset_id) }}" class="hover:underline">{{ $disposal->asset?->name ?? 'Asset #' . $disposal->asset_id }}</a>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $disposal->disposalType?->name }} disposal request</p>
        </div>
    </div>

    <div class="max-w-2xl space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                @php
                    $statusColor = match($disposal->status) {
                        'scrapped'    => 'gray',
                        'written_off' => 'blue',
                        'approved'    => 'green',
                        'rejected'    => 'red',
                        default       => 'amber',
                    };
                @endphp
                <x-status-badge :color="$statusColor" :label="ucwords(str_replace('_', ' ', $disposal->status))" />
                @if($disposal->approvalRequest)
                    <a href="{{ route('approvals.show', $disposal->approvalRequest) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">View approval progress</a>
                @endif
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Requested By</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $disposal->requestedBy?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Requested On</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $disposal->created_at->format('d M Y') }}</dd></div>
                <div class="col-span-2"><dt class="text-gray-400 text-xs uppercase tracking-wide">Reason</dt><dd class="text-gray-700 dark:text-gray-300 mt-0.5">{{ $disposal->reason }}</dd></div>

                @if($disposal->written_off_at)
                    <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Written Off</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $disposal->written_off_at->format('d M Y') }} by {{ $disposal->writtenOffBy?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Disposal Value</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $disposal->disposal_value !== null ? number_format($disposal->disposal_value, 2) : '—' }}</dd></div>
                @endif

                @if($disposal->scrapped_at)
                    <div class="col-span-2"><dt class="text-gray-400 text-xs uppercase tracking-wide">Scrapped</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $disposal->scrapped_at->format('d M Y') }} by {{ $disposal->scrappedBy?->name ?? '—' }}</dd></div>
                @endif
            </dl>
        </div>

        @can('disposal.complete')
            @if($disposal->status === 'approved')
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Write Off</p>
                    <form method="POST" action="{{ route('disposals.write-off', $disposal) }}" class="flex items-end gap-3">
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="disposal_value" value="Disposal Value" />
                            <x-text-input id="disposal_value" name="disposal_value" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        </div>
                        <x-primary-button type="submit">Write Off</x-primary-button>
                    </form>
                    <x-input-error :messages="$errors->get('disposal_value')" class="mt-1" />
                </div>
            @endif

            @if($disposal->status === 'written_off')
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Complete Scrap</p>
                    <p class="text-xs text-gray-400 mb-4">Sets the asset's status to Disposed and permanently blocks movement/reassignment.</p>
                    <x-confirm-modal :action="route('disposals.scrap', $disposal)"
                                      method="POST"
                                      title="Mark this disposal scrapped?"
                                      message="The asset's status will be set to Disposed. This cannot be undone."
                                      confirm-label="Confirm Scrap"
                                      trigger-class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        Complete Scrap
                    </x-confirm-modal>
                </div>
            @endif
        @endcan
    </div>
</x-app-layout>
