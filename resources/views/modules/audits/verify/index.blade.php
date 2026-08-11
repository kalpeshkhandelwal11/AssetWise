<x-app-layout>
    @section('page-title', 'Verify Assets')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Verify Assets</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Scan a QR tag or search below to mark an asset verified, missing, or damaged.</p>
    </div>

    @if($campaigns->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-8 text-center text-sm text-gray-400">
            You are not assigned to any active audit campaign right now.
        </div>
    @else
        <form method="GET" action="{{ route('audits.verify') }}" class="flex flex-wrap gap-3 mb-5">
            <select name="campaign" onchange="this.form.submit()"
                    class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @foreach($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected(optional($selectedCampaign)->id === $campaign->id)>{{ $campaign->name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search asset name or tag…"
                   class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                @foreach(['pending' => 'Pending', 'verified' => 'Verified', 'missing' => 'Missing', 'damaged' => 'Damaged'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Filter</button>
        </form>

        @if($selectedCampaign)
            <div class="space-y-3">
                @forelse($items as $item)
                    @php
                        $itemColor = match($item->status) {
                            'verified' => 'green', 'missing' => 'red', 'damaged' => 'amber', default => 'gray',
                        };
                    @endphp
                    <details id="item-{{ $item->id }}" class="bg-white dark:bg-gray-800 rounded-xl border {{ $focusItemId === $item->id ? 'border-indigo-400 ring-2 ring-indigo-200 dark:ring-indigo-800' : 'border-gray-200 dark:border-gray-700' }} shadow-sm" @if($focusItemId === $item->id) open @endif>
                        <summary class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer select-none">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $item->asset?->name ?? 'Asset #'.$item->asset_id }}</p>
                                <p class="text-xs text-gray-400">{{ $item->asset?->asset_tag }} · expected {{ $item->expectedLocation?->name ?? '—' }} / {{ $item->expectedCustodian?->name ?? '—' }}</p>
                            </div>
                            <x-status-badge :color="$itemColor" :label="ucwords($item->status)" />
                        </summary>

                        <div class="px-4 pb-4 border-t border-gray-100 dark:border-gray-700 pt-4">
                            @if($item->status !== 'pending')
                                <p class="text-xs text-gray-400 mb-3">
                                    Verified {{ optional($item->verified_at)->format('d M Y H:i') }} by {{ $item->verifiedBy?->name ?? '—' }}
                                    @if($item->notes) — "{{ $item->notes }}" @endif
                                </p>
                            @endif

                            <form method="POST" action="{{ route('audits.items.verify', $item) }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <div class="flex flex-wrap gap-4">
                                    @foreach(['verified' => 'Verified', 'missing' => 'Missing', 'damaged' => 'Damaged'] as $value => $label)
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="radio" name="status" value="{{ $value }}" required class="text-indigo-600 focus:ring-indigo-500">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                <div>
                                    <textarea name="notes" rows="2" placeholder="Notes (required for missing/damaged)"
                                              class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                                </div>
                                <div>
                                    <input type="file" name="photo" accept="image/*" class="text-sm text-gray-600 dark:text-gray-400">
                                </div>
                                <x-primary-button type="submit">Submit Verification</x-primary-button>
                            </form>
                        </div>
                    </details>
                @empty
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-8 text-center text-sm text-gray-400">
                        No matching audit items.
                    </div>
                @endforelse
            </div>

            @if(method_exists($items, 'hasPages') && $items->hasPages())
                <div class="mt-4">{{ $items->links() }}</div>
            @endif
        @endif
    @endif

    @if($focusItemId)
        <script>
            document.getElementById('item-{{ $focusItemId }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        </script>
    @endif
</x-app-layout>
