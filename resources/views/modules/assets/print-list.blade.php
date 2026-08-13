<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Asset List</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #666; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #555; }
        td.mono { font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Asset List</h1>
    <p class="meta">{{ $assets->count() }} asset(s) &middot; generated {{ now()->format('d M Y, H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Asset ID</th>
                <th>Name</th>
                <th>Company</th>
                <th>Status</th>
                <th>Location</th>
                <th>Custodian</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assets as $asset)
                @php $loc = collect([$asset->location?->name, $asset->building?->name, $asset->room?->name])->filter(); @endphp
                <tr>
                    <td class="mono">{{ $asset->asset_tag ?? '—' }}</td>
                    <td>{{ $asset->name }}</td>
                    <td>{{ $asset->company?->name ?? '—' }}</td>
                    <td>{{ $asset->status?->name ?? '—' }}</td>
                    <td>{{ $loc->isNotEmpty() ? $loc->join(' · ') : '—' }}</td>
                    <td>{{ $asset->custodian?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
