<x-app-layout>
    @section('page-title', 'Receive Batch')

    @php
        $received = $members->where('receipt_status', 'received')->count();
        $pending = $members->where('receipt_status', 'in_transit')->count();
        $short = $members->where('receipt_status', 'short')->count();
        $scanViewerId = 'batch-viewer-'.\Illuminate\Support\Str::random(8);
    @endphp

    <div class="max-w-3xl space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('movements.incoming') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Receive Batch</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $members->first()?->movementType?->name }} · {{ $members->count() }} asset(s) ·
                    <span class="text-green-600">{{ $received }} received</span> ·
                    <span class="text-amber-600">{{ $pending }} awaiting</span>
                    @if($short) · <span class="text-red-600">{{ $short }} short</span>@endif
                </p>
            </div>
        </div>

        @if($pending > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <form method="POST" action="{{ route('movements.batches.scan', $batch) }}"
                      x-data="tagScanner(@js(['viewerId' => $scanViewerId, 'inputId' => 'tag_number', 'autoSubmit' => true]))">
                    @csrf
                    <x-input-label for="tag_number" value="Scan each asset as it arrives" />
                    <div class="mt-1 flex gap-2">
                        <input id="tag_number" name="tag_number" type="text" autocomplete="off" autofocus
                               placeholder="Scan or type tag / Asset ID, then Enter"
                               class="flex-1 min-w-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono" />
                        <button type="button" @click="toggle()" :disabled="starting"
                                class="flex-shrink-0 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700">
                            <span x-text="active ? 'Stop' : (starting ? '…' : 'Camera')"></span>
                        </button>
                        <x-primary-button>Receive</x-primary-button>
                    </div>
                    <x-input-error :messages="$errors->get('tag_number')" class="mt-1" />
                    <div x-show="active" x-cloak class="mt-3">
                        <div id="{{ $scanViewerId }}" class="w-full max-w-sm overflow-hidden rounded-lg bg-black"></div>
                    </div>
                </form>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    An asset that arrived incomplete? Open it below and report it. Anything still unscanned when you finish is reported short (missing).
                </p>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                @foreach($members as $member)
                    @php
                        $state = ['received' => ['Received', 'green'], 'in_transit' => ['Awaiting', 'yellow'], 'short' => ['Short', 'red'],
                                  'lost' => ['Lost', 'gray'], 'returned' => ['Returned', 'gray']][$member->receipt_status] ?? [$member->display_status, 'gray'];
                    @endphp
                    <li class="px-6 py-3 flex items-center justify-between gap-3 {{ $member->receipt_status === 'received' ? 'bg-green-50/50 dark:bg-green-900/10' : '' }}">
                        <div>
                            <p class="text-gray-900 dark:text-gray-100">{{ \App\Models\Asset::labelFor($member->asset) }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $member->asset?->asset_tag }} {{ $member->asset?->activeTag()?->tag_number ? '· '.$member->asset->activeTag()->tag_number : '' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-status-badge :color="$state[1]" :label="$state[0]" :dot="false" />
                            @if($member->receipt_status === 'in_transit')
                                <a href="{{ route('movements.receive.form', $member) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Details / incomplete</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        @if($pending > 0)
            <form method="POST" action="{{ route('movements.batches.finish', $batch) }}"
                  class="bg-white dark:bg-gray-800 rounded-xl border border-amber-200 dark:border-amber-800 shadow-sm p-6 space-y-3">
                @csrf
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Finish receipt — the {{ $pending }} asset(s) still awaiting will be reported <span class="font-medium">short (not received)</span> for an administrator to resolve.
                </p>
                <textarea name="notes" rows="2" placeholder="Optional note, e.g. delivery van had only 3 boxes"
                          class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm"></textarea>
                <button type="submit" class="px-4 py-2 text-sm font-medium bg-amber-600 text-white rounded-lg hover:bg-amber-700">Finish receipt</button>
            </form>
        @endif
    </div>
</x-app-layout>
