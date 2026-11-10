{{-- DomPDF: inline/print styles only. Expects $title, $subtitle, $company, $meta (label => value). --}}
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 9px; color: #111; margin: 0; }
    h1 { font-size: 14px; margin: 0 0 2px; }
    .muted { color: #555; }
    table { width: 100%; border-collapse: collapse; }
    .grid td { border: 1px solid #999; padding: 3px 4px; vertical-align: top; }
    .grid th { border: 1px solid #999; padding: 4px; background: #eee; text-align: left; font-size: 8px; text-transform: uppercase; }
    .section td { background: #f4f4f4; font-weight: bold; }
    .right { text-align: right; }
    .total td { font-weight: bold; background: #f0f0ff; }
    .sign td { border: none; padding: 24px 8px 0; width: 33%; vertical-align: bottom; }
    .sign .line { border-top: 1px solid #333; padding-top: 3px; }
    .draft { position: fixed; top: 40%; left: 25%; font-size: 70px; color: rgba(200, 0, 0, 0.12); transform: rotate(-25deg); }
</style>
<table style="margin-bottom: 8px;">
    <tr>
        <td>
            <h1>{{ $title }}</h1>
            <div class="muted">{{ $subtitle }}</div>
        </td>
        <td class="right">
            <strong>{{ $company?->legal_name ?: $company?->name }}</strong><br>
            @if($company?->cin)<span class="muted">CIN {{ $company->cin }}</span><br>@endif
            @if($company?->registered_address)<span class="muted">{{ $company->registered_address }}</span>@endif
        </td>
    </tr>
</table>
<table style="margin-bottom: 8px;">
    <tr>
        @foreach($meta as $label => $value)
            <td><span class="muted">{{ $label }}</span><br><strong>{{ $value ?: '—' }}</strong></td>
        @endforeach
    </tr>
</table>
