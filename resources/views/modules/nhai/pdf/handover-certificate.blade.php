<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $handover->reference_no }}</title></head>
<body>
    @if($handover->status !== 'approved')<div class="draft">{{ strtoupper(str_replace('_', ' ', $handover->status)) }}</div>@endif

    @include('modules.nhai.pdf.partials.header', [
        'title'    => 'Plaza Handover Certificate',
        'subtitle' => $handover->location?->name.($handover->location?->chainage ? ' · Chainage '.$handover->location->chainage : ''),
        'company'  => $handover->company,
        'meta'     => [
            'Reference'    => $handover->reference_no,
            'Date'         => $handover->handover_date?->format('d M Y'),
            'Handed over by' => $handover->company?->name,
            'To'           => $handover->authority?->name,
            'Document no.' => $handover->document_no,
        ],
    ])

    @foreach(\App\Models\PlazaHandover::ACTIONS as $action => $label)
        @php $group = $handover->lines->where('action', $action); @endphp
        @continue($group->isEmpty())
        <table class="grid" style="margin-bottom: 8px;">
            <thead>
                <tr class="section"><td colspan="{{ $showPrices ? 7 : 5 }}">{{ $label }} ({{ $group->count() }})</td></tr>
                <tr>
                    <th>Asset tag</th><th>Item</th><th>Serial</th><th>Condition / remarks</th>
                    @if($showPrices)<th class="right">Book value</th><th class="right">{{ $action === 'sell' ? 'Sale price' : '' }}</th>@endif
                    <th>{{ $action === 'sell' ? 'Buyer' : ($action === 'move' ? 'To plaza' : '') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group as $line)
                    <tr>
                        <td>{{ $line->asset?->asset_tag }}</td>
                        <td>{{ \App\Models\Asset::labelFor($line->asset) }}<br><span class="muted">{{ $line->asset?->category?->name }}</span></td>
                        <td>{{ $line->asset?->serial_number }}</td>
                        <td>{{ \App\Models\PlazaTakeoverLine::CONDITIONS[$line->condition] ?? $line->condition }} {{ $line->remarks }}</td>
                        @if($showPrices)
                            <td class="right">{{ $line->nbv_snapshot !== null ? number_format((float) $line->nbv_snapshot, 2) : '' }}</td>
                            <td class="right">{{ $action === 'sell' ? number_format((float) $line->sale_price, 2) : '' }}</td>
                        @endif
                        <td>{{ $action === 'sell' ? ($line->buyer?->name ?? $line->buyer_name) : ($action === 'move' ? $line->toLocation?->name : '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if($handover->remarks)<p><strong>Remarks:</strong> {{ $handover->remarks }}</p>@endif

    @include('modules.nhai.pdf.partials.signatures', ['signatories' => $handover->signatories ?? []])
</body>
</html>
