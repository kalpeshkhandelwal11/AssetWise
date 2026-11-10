<x-app-layout>
    @section('page-title', 'Short Received')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Short Received</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
            Assets not received, or received incomplete. Each stays In Transit until resolved here.
        </p>
    </div>

    <div class="space-y-4">
        @forelse($movements as $movement)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5"
                 x-data="{ open: false }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">
                            {{ \App\Models\Asset::labelFor($movement->asset) }}
                            <span class="text-xs text-gray-400 font-mono">{{ $movement->asset?->asset_tag }}</span>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $movement->movementType?->name }} →
                            {{ collect([$movement->toCompany?->name, $movement->toLocation?->name, $movement->toCustodian?->name])->filter()->join(' · ') ?: 'Stores' }}
                            · reported {{ $movement->short_reported_at?->diffForHumans() }} by {{ $movement->shortReportedBy?->name ?? 'System' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-status-badge :color="$movement->short_type === 'incomplete' ? 'amber' : 'red'"
                                        :label="$movement->short_type === 'incomplete' ? 'Incomplete' : 'Not received'" :dot="false" />
                        @if($movement->resolution === 'lost')
                            <x-status-badge color="gray" label="Write-off pending approval" :dot="false" />
                        @else
                            <button type="button" @click="open = ! open" class="px-3 py-1.5 text-xs font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Resolve</button>
                        @endif
                    </div>
                </div>

                @if($movement->verification_notes)
                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $movement->verification_notes }}</p>
                @endif
                @if(! empty($movement->verification_photos))
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach($movement->verification_photos as $photo)
                            <a href="{{ Storage::url($photo) }}" target="_blank"><img src="{{ Storage::url($photo) }}" alt="Receipt photo" class="w-14 h-14 rounded object-cover border border-gray-200 dark:border-gray-700"></a>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('movements.short.resolve', $movement) }}" x-show="open" x-cloak
                      class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                        @foreach(\App\Models\AssetMovement::RESOLUTIONS as $value => $label)
                            @continue($value === 'accepted_as_is' && $movement->short_type !== 'incomplete')
                            <label class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                <input type="radio" name="resolution" value="{{ $value }}" required class="text-indigo-600 border-gray-300">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <textarea name="resolution_notes" rows="2" required placeholder="What happened? (required)"
                              class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm"></textarea>
                    @error('workflow')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <x-primary-button>Save resolution</x-primary-button>
                </form>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center text-sm text-gray-400">
                No open short-received issues.
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $movements->links() }}</div>
</x-app-layout>
