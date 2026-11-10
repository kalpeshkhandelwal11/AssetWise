{{-- Compact receipt summary for history tables. Expects $movement. --}}
@if($movement->verified_at || $movement->isShort())
    @php
        $condMap = [
            'ok'         => ['Received OK', 'green'],
            'damaged'    => ['Damaged', 'amber'],
            'missing'    => ['Missing / short', 'red'],
            'incomplete' => ['Incomplete', 'amber'],
        ];
        $cond = $movement->verification_condition;
    @endphp
    <div class="flex flex-col gap-1">
        @if($cond && isset($condMap[$cond]))
            <x-status-badge :color="$condMap[$cond][1]" :label="$condMap[$cond][0]" />
        @else
            <span class="text-xs text-gray-400">Verified</span>
        @endif

        @if($movement->received_at)
            <p class="text-xs text-gray-400">
                {{ $movement->receipt_method === 'on_behalf' ? 'Confirmed on behalf' : 'Scanned in' }}
                by {{ $movement->receivedBy?->name ?? '—' }}, {{ $movement->received_at->format('d M Y') }}
            </p>
        @endif

        @if($movement->resolution)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Resolution: {{ \App\Models\AssetMovement::RESOLUTIONS[$movement->resolution] ?? $movement->resolution }}
                @if($movement->resolution_notes) — {{ $movement->resolution_notes }}@endif
            </p>
        @endif

        @if($movement->verification_notes)
            <p class="text-xs text-gray-500 dark:text-gray-400 max-w-[16rem]">{{ $movement->verification_notes }}</p>
        @endif

        @if(!empty($movement->verification_photos))
            <div class="flex flex-wrap gap-1 mt-0.5">
                @foreach($movement->verification_photos as $photo)
                    <a href="{{ Storage::url($photo) }}" target="_blank" title="Receipt photo">
                        <img src="{{ Storage::url($photo) }}" alt="Receipt photo"
                             class="w-9 h-9 rounded object-cover border border-gray-200 dark:border-gray-700" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@elseif($movement->isInTransit())
    <span class="text-xs text-indigo-600 dark:text-indigo-400">Awaiting receipt</span>
@else
    <span class="text-xs text-gray-300 dark:text-gray-600">—</span>
@endif
