<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $takeover->reference_no }}</title></head>
<body>
    @if(! in_array($takeover->status, ['approved'], true))<div class="draft">{{ strtoupper(str_replace('_', ' ', $takeover->status)) }}</div>@endif

    @include('modules.nhai.pdf.partials.header', [
        'title'    => ($takeover->isNhai() ? 'Takeover Certificate — Assets received from NHAI' : 'Takeover Certificate — Assets purchased'),
        'subtitle' => $takeover->location?->name.($takeover->location?->chainage ? ' · Chainage '.$takeover->location->chainage : ''),
        'company'  => $takeover->company,
        'meta'     => [
            'Reference'      => $takeover->reference_no,
            'Date'           => $takeover->takeover_date?->format('d M Y'),
            'Handed over by' => $takeover->fromCompany?->name,
            'Received by'    => $takeover->company?->name,
            'Custodian'      => $takeover->custodian?->name,
            'Document no.'   => $takeover->document_no,
        ],
    ])

    <table class="grid">
        <thead>
            <tr>
                <th>S.No</th><th>Item / equipment</th><th>Make / model</th><th>Serial / position</th><th>Qty</th>
                @if($showPrices)<th class="right">Unit price</th><th class="right">Amount</th>@endif
                <th>Location</th><th>Condition</th><th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @php $section = null; $n = 0; @endphp
            @foreach($takeover->lines as $line)
                @if($line->section && $line->section !== $section)
                    @php $section = $line->section; @endphp
                    <tr class="section"><td colspan="{{ $showPrices ? 10 : 8 }}">{{ $section }}</td></tr>
                @endif
                <tr>
                    <td>{{ ++$n }}</td>
                    <td>{{ $line->item_name }}<br><span class="muted">{{ $line->category?->name }}</span></td>
                    <td>{{ collect([$line->manufacturer, $line->model])->filter()->join(' / ') }}</td>
                    <td>{{ $line->serial_number }} {{ $line->position_label }}</td>
                    <td class="right">{{ $line->quantity }}</td>
                    @if($showPrices)
                        <td class="right">{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="right">{{ number_format((float) $line->unit_price * $line->quantity, 2) }}</td>
                    @endif
                    <td>{{ collect([$line->room?->name, $line->location_note])->filter()->join(' · ') }}</td>
                    <td>{{ \App\Models\PlazaTakeoverLine::CONDITIONS[$line->condition] ?? $line->condition }}{{ $line->not_functional_qty ? ' ('.$line->not_functional_qty.' not working)' : '' }}</td>
                    <td>{{ $line->remarks }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">Total</td>
                <td class="right">{{ $takeover->totalUnits() }}</td>
                @if($showPrices)<td></td><td class="right">{{ number_format($takeover->totalValue(), 2) }}</td>@endif
                <td colspan="3">{{ $takeover->isNhai() ? 'NHAI property — no consideration' : '' }}</td>
            </tr>
        </tbody>
    </table>

    @if($takeover->remarks)<p style="margin-top: 8px;"><strong>Remarks:</strong> {{ $takeover->remarks }}</p>@endif

    @include('modules.nhai.pdf.partials.signatures', ['signatories' => $takeover->signatories ?? []])
</body>
</html>
