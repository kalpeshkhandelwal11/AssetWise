<x-app-layout>
    @section('page-title', 'Verify Assets')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Verify Assets</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Scan a QR tag or search below to mark an asset verified, missing, or damaged.</p>
    </div>

    {{-- M15 — same component as /scan. Scanning an in-scope tag lands back here with that
         item focused, via the branch M10 already built into ScanController::resolve(). --}}
    @can('tags.view')
        <div class="mb-6 max-w-lg">
            <x-qr-scanner heading="Scan to verify" hint="Scan an asset's tag to jump straight to its checklist item below." />
        </div>
    @endcan

    @if($campaigns->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-8 text-center text-sm text-gray-400">
            You are not assigned to any active audit campaign right now.
        </div>
    @else
        {{-- M15 responsive pass: the controls kept their intrinsic widths and wrapped into a
             ragged stack at 375px. Full-width on phones, inline from sm up. --}}
        <form method="GET" action="{{ route('audits.verify') }}" class="flex flex-wrap gap-3 mb-5">
            <select name="campaign" onchange="this.form.submit()"
                    class="w-full sm:w-auto text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @foreach($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected(optional($selectedCampaign)->id === $campaign->id)>{{ $campaign->name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search asset name or tag…"
                   class="w-full sm:w-auto text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <select name="status" class="w-full sm:w-auto text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                @foreach(['pending' => 'Pending', 'verified' => 'Verified', 'missing' => 'Missing', 'damaged' => 'Damaged'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="w-full sm:w-auto px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Filter</button>
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
                                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ \App\Models\Asset::labelFor($item->asset) }}</p>
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

                            @php
                                $tagged = $item->asset?->activeTag() !== null;
                                $scanViewer = 'audit-scan-' . $item->id;
                                $scanInput = 'audit-tag-' . $item->id;
                                $mine = (int) session('audit_item_error') === (int) $item->id;
                                $prefill = $mine ? old('tag_number') : ((int) $focusItemId === (int) $item->id ? request('scanned') : null);
                            @endphp
                            <form method="POST" action="{{ route('audits.items.verify', $item) }}" enctype="multipart/form-data" class="space-y-3"
                                  x-data="{ ...tagScanner(@js(['viewerId' => $scanViewer, 'inputId' => $scanInput])), status: @js($mine ? old('status', '') : '') }">
                                @csrf
                                <div class="flex flex-wrap gap-4">
                                    @foreach(['verified' => 'Verified', 'missing' => 'Missing', 'damaged' => 'Damaged'] as $value => $label)
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="radio" name="status" value="{{ $value }}" required x-model="status" class="text-indigo-600 focus:ring-indigo-500">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>

                                {{-- In hand (verified / damaged): confirm it is this asset, then photograph it. --}}
                                <div x-show="status !== 'missing'" class="space-y-3">
                                    <div>
                                        <label for="{{ $scanInput }}" class="block text-xs font-medium text-gray-600 dark:text-gray-400">
                                            {{ $tagged ? 'Scan the tag on the asset *' : 'Asset ID * (this asset has no tag)' }}
                                        </label>
                                        <div class="mt-1 flex gap-2">
                                            <input id="{{ $scanInput }}" name="tag_number" type="text" autocomplete="off" @keydown.enter.prevent
                                                   value="{{ $prefill }}" placeholder="{{ $tagged ? 'Scan the tag' : 'Asset ID' }}"
                                                   :required="status !== 'missing'"
                                                   class="flex-1 min-w-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono" />
                                            <button type="button" @click="toggle()" :disabled="starting"
                                                    class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-60">
                                                <span x-text="active ? 'Stop' : (starting ? '…' : 'Scan')"></span>
                                            </button>
                                        </div>
                                        <div x-show="active" x-cloak class="mt-2">
                                            <div id="{{ $scanViewer }}" class="w-full max-w-sm overflow-hidden rounded-lg bg-black"></div>
                                        </div>
                                        <p x-show="error" x-cloak x-text="error" class="mt-1 text-xs text-red-600"></p>
                                        @if($mine)
                                            <x-input-error :messages="$errors->get('tag_number')" class="mt-1" />
                                        @endif
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Photo of the asset *</label>
                                        {{-- capture opens the phone camera directly; on a computer it is a file picker. --}}
                                        <input type="file" name="photo" accept="image/*" capture="environment" data-compress :required="status !== 'missing'"
                                               class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        @if($mine)
                                            <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                                        @endif
                                    </div>
                                </div>
                                <p x-show="status === 'missing'" x-cloak class="text-xs text-gray-500 dark:text-gray-400">Missing: no scan or photo — describe where it should be in the note.</p>

                                <div>
                                    <textarea name="notes" rows="2" placeholder="Notes (required for missing/damaged)"
                                              :required="status === 'missing' || status === 'damaged'"
                                              class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ $mine ? old('notes') : '' }}</textarea>
                                    @if($mine)
                                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                                    @endif
                                </div>
                                {{-- Anything refused for this item that is not a field above (e.g. not an assigned auditor). --}}
                                @if($mine)
                                    @foreach(collect($errors->getMessages())->except(['tag_number', 'photo', 'notes'])->flatten() as $itemError)
                                        <p class="text-sm text-red-600 dark:text-red-400">{{ $itemError }}</p>
                                    @endforeach
                                @endif
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
