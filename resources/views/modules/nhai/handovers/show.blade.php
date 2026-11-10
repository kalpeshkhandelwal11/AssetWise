<x-app-layout>
    @php
        $draft = $handover->isDraft();
        $lines = $handover->lines;
        $count = fn ($action) => $lines->where('action', $action)->count();
        $salesTotal = $lines->where('action', 'sell')->sum(fn ($l) => (float) $l->sale_price);
        $nbvWrittenOff = $lines->where('action', 'give_to_nhai')->sum(fn ($l) => (float) $l->nbv_snapshot);
        $saleNbv = $lines->where('action', 'sell')->sum(fn ($l) => (float) $l->nbv_snapshot);
        $canEdit = $draft && auth()->user()->can('handover.manage');
    @endphp
    @section('page-title', 'Handover '.$handover->reference_no)

    <x-breadcrumb :items="[['label' => 'NHAI / Toll Plazas', 'url' => route('nhai.index', ['tab' => 'handovers'])], ['label' => $handover->reference_no]]" class="mb-4" />

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Plaza handover · <span class="font-mono">{{ $handover->reference_no }}</span></h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                {{ $handover->location?->name }} · {{ $handover->company?->name }} → {{ $handover->authority?->name }} · {{ $handover->handover_date?->format('d M Y') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @include('modules.nhai.partials.status', ['status' => $handover->status])
            <a href="{{ route('nhai.handovers.certificate', $handover) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200">Certificate (PDF)</a>
            @can('handover.manage')
                @if($draft)
                    <form method="POST" action="{{ route('nhai.handovers.refresh', $handover) }}">@csrf
                        <button class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200">Refresh asset list</button>
                    </form>
                @elseif($handover->status === 'rejected')
                    <form method="POST" action="{{ route('nhai.handovers.reopen', $handover) }}">@csrf
                        <button class="px-3 py-1.5 text-xs font-medium bg-amber-600 text-white rounded-lg">Reopen as draft</button>
                    </form>
                @elseif($handover->status === 'apply_failed')
                    <form method="POST" action="{{ route('nhai.handovers.retry', $handover) }}">@csrf
                        <button class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-lg">Retry apply</button>
                    </form>
                @endif
            @endcan
            @if($handover->approvalRequest)
                <a href="{{ route('approvals.show', $handover->approvalRequest) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Approval request</a>
            @endif
        </div>
    </div>

    @if($handover->apply_error)
        <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-sm text-red-700 dark:text-red-300">Applying stopped: {{ $handover->apply_error }}</div>
    @endif
    @foreach(['workflow', 'lines', 'status'] as $key)
        @error($key)<div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-sm text-red-700 dark:text-red-300">{{ $message }}</div>@enderror
    @endforeach
    @if($blockers)
        <div class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-sm text-amber-800 dark:text-amber-300">
            {{ count($blockers) }} asset(s) must be dealt with before this can be submitted — they are flagged below.
        </div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6 text-sm">
        @foreach([
            ['Return to NHAI', $count('return_to_nhai')],
            ['Give to NHAI', $count('give_to_nhai')],
            ['Sell', $count('sell')],
            ['Move', $count('move')],
            ['Sale total', '₹ '.number_format($salesTotal, 2)],
        ] as [$label, $value])
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $label }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    @if($nbvWrittenOff || $saleNbv)
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
            Book value written off on assets given to NHAI: ₹ {{ number_format($nbvWrittenOff, 2) }} ·
            expected gain/loss on sales: ₹ {{ number_format($salesTotal - $saleNbv, 2) }} (sale price − book value at draft time).
        </p>
    @endif

    <form method="POST" action="{{ route('nhai.handovers.lines.update', $handover) }}">
        @csrf @method('PUT')
        <x-data-table>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-3 py-3 text-left">Asset</th>
                        <th class="px-3 py-3 text-left">Owner</th>
                        <th class="px-3 py-3 text-right">Book value</th>
                        <th class="px-3 py-3 text-left">Action</th>
                        <th class="px-3 py-3 text-left">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($lines as $line)
                        @php $external = $line->asset?->isExternallyOwned(); $field = "lines[{$line->id}]"; @endphp
                        <tr class="align-top {{ isset($blockers[$line->asset_id]) ? 'bg-amber-50/60 dark:bg-amber-900/10' : '' }}"
                            x-data="{ action: @js(old("lines.{$line->id}.action", $line->action)) }">
                            <td class="px-3 py-2">
                                <span class="text-gray-900 dark:text-gray-100">{{ \App\Models\Asset::labelFor($line->asset) }}</span>
                                <span class="block text-xs text-gray-400 font-mono">{{ $line->asset?->asset_tag }} {{ $line->asset?->serial_number }}</span>
                                @isset($blockers[$line->asset_id])<span class="block text-xs text-amber-700">⚠ {{ $blockers[$line->asset_id] }}</span>@endisset
                                @if($line->applied_at)<span class="block text-xs text-green-600">Applied {{ $line->applied_at->format('d M Y') }}</span>@endif
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $external ? 'NHAI' : 'Ours' }} · {{ $line->asset?->category?->name }}</td>
                            <td class="px-3 py-2 text-right text-xs text-gray-600 dark:text-gray-400">{{ $line->nbv_snapshot !== null ? number_format((float) $line->nbv_snapshot, 2) : '—' }}</td>
                            <td class="px-3 py-2">
                                @if($canEdit)
                                    <select name="{{ $field }}[action]" x-model="action" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        @foreach(\App\Models\PlazaHandover::ACTIONS as $value => $label)
                                            @continue($external ? $value !== 'return_to_nhai' : $value === 'return_to_nhai')
                                            <option value="{{ $value }}">{{ $label }}{{ $value === 'give_to_nhai' ? ' — suggested' : '' }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-gray-700 dark:text-gray-300">{{ \App\Models\PlazaHandover::ACTIONS[$line->action] ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($canEdit)
                                    <div x-show="action === 'sell'" class="flex flex-wrap gap-2">
                                        <input type="number" step="0.01" min="0" name="{{ $field }}[sale_price]" value="{{ old("lines.{$line->id}.sale_price", $line->sale_price) }}" placeholder="Sale price ₹"
                                               class="w-32 text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        <select name="{{ $field }}[buyer_company_id]" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            <option value="">Buyer (party)…</option>
                                            @foreach($buyers as $buyer)<option value="{{ $buyer->id }}" @selected($line->buyer_company_id == $buyer->id)>{{ $buyer->name }}</option>@endforeach
                                        </select>
                                        <input type="text" name="{{ $field }}[buyer_name]" value="{{ $line->buyer_name }}" placeholder="…or buyer name"
                                               class="w-40 text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    </div>
                                    <div x-show="action === 'move'" class="flex flex-wrap gap-2">
                                        <select name="{{ $field }}[to_location_id]" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            <option value="">To plaza…</option>
                                            @foreach($locations as $location)<option value="{{ $location->id }}" @selected($line->to_location_id == $location->id)>{{ $location->name }}</option>@endforeach
                                        </select>
                                        <select name="{{ $field }}[to_custodian_id]" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            <option value="">Custodian (optional)</option>
                                            @foreach($employees as $employee)<option value="{{ $employee->id }}" @selected($line->to_custodian_id == $employee->id)>{{ $employee->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <p x-show="action === 'give_to_nhai'" class="text-xs text-gray-500">No money — written off at zero proceeds.</p>
                                    <p x-show="action === 'return_to_nhai'" class="text-xs text-gray-500">NHAI property — returned.</p>
                                    <input type="text" name="{{ $field }}[remarks]" value="{{ $line->remarks }}" placeholder="Remarks"
                                           class="mt-1 w-full text-xs rounded-md border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @else
                                    <span class="text-xs text-gray-600 dark:text-gray-400">
                                        @if($line->action === 'sell') ₹ {{ number_format((float) $line->sale_price, 2) }} to {{ $line->buyer?->name ?? $line->buyer_name }}
                                        @elseif($line->action === 'move') to {{ $line->toLocation?->name }}
                                        @endif
                                        {{ $line->remarks }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">No assets of this company at this plaza.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>

        @if($canEdit && $lines->isNotEmpty())
            <div class="mt-4 flex flex-wrap justify-end gap-3">
                <x-primary-button>Save choices</x-primary-button>
                <button type="submit" name="submit_after" value="1"
                        class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        onclick="return confirm('Save and submit this handover for approval? Its assets are locked until it is decided.')">Save & submit for approval</button>
            </div>
        @endif
    </form>
</x-app-layout>
