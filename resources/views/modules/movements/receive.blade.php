<x-app-layout>
    @section('page-title', 'Receive Asset')

    <div class="max-w-lg">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('movements.incoming') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Receive Asset</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $movement->movementType?->name }} · in transit since {{ $movement->dispatched_at?->format('d M Y, H:i') }}</p>
            </div>
        </div>

        @if($movement->batch_id)
            <p class="mb-4 text-sm">
                <a href="{{ route('movements.batches.receive', $movement->batch_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">This asset is part of a batch — receive the whole batch</a>
            </p>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <dl class="grid grid-cols-2 gap-4 text-sm mb-6">
                <div>
                    <dt class="text-xs text-gray-400 uppercase tracking-wide">Asset</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ \App\Models\Asset::labelFor($movement->asset) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 uppercase tracking-wide">Asset ID</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-0.5 font-mono">{{ $movement->asset?->asset_tag ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 uppercase tracking-wide">Tag / Barcode</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-0.5 font-mono">{{ $movement->asset?->activeTag()?->tag_number ?? '— none —' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400 uppercase tracking-wide">Destination</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-0.5">
                        {{ collect([$movement->toCompany?->name, $movement->toLocation?->name, $movement->toCustodian?->name])->filter()->join(' · ') ?: 'Stores (return)' }}
                    </dd>
                </div>
            </dl>

            @php $receiveViewerId = 'receive-viewer-'.\Illuminate\Support\Str::random(8); @endphp
            <form method="POST" action="{{ route('movements.receive', $movement) }}" enctype="multipart/form-data"
                  x-data="tagScanner(@js(['viewerId' => $receiveViewerId, 'inputId' => 'tag_number']))">
                @csrf

                @if($canOnBehalf)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 mb-3">
                        <input type="checkbox" name="on_behalf" value="1" @checked(old('on_behalf'))
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               @change="$refs.scanBlock.style.display = $event.target.checked ? 'none' : ''">
                        Confirm on behalf of the receiver (no scan)
                    </label>
                @endif

                <div x-ref="scanBlock">
                    <x-input-label for="tag_number" value="Scan or type the asset's barcode to confirm it arrived" />
                    <div class="mt-1 flex gap-2">
                        <input id="tag_number" name="tag_number" type="text" autocomplete="off" @keydown.enter.prevent
                               value="{{ old('tag_number') }}" placeholder="Scan or type tag / Asset ID"
                               class="flex-1 min-w-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono" />
                        <button type="button" @click="toggle()" :disabled="starting"
                                class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-60 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span x-text="active ? 'Stop' : (starting ? '…' : 'Scan')"></span>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('tag_number')" class="mt-1" />
                    <div x-show="active" x-cloak class="mt-3">
                        <div id="{{ $receiveViewerId }}" class="w-full max-w-sm overflow-hidden rounded-lg bg-black"></div>
                    </div>
                    <p x-show="error" x-cloak x-text="error"
                       class="mt-2 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2"></p>
                </div>

                <div class="mt-4">
                    @include('modules.movements.partials.receipt-fields', ['conditions' => [
                        'ok'         => 'Received OK',
                        'damaged'    => 'Received damaged',
                        'incomplete' => 'Incomplete / short (parts missing)',
                    ], 'requireCondition' => true])
                    <x-primary-button type="submit">Confirm receipt</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
