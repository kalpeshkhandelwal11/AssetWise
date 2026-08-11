<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #1f2937; }
        h2 { margin-bottom: 2px; }
        .meta { color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
        th { background-color: #f3f4f6; font-size: 9px; text-transform: uppercase; }
        tr:nth-child(even) { background-color: #f9fafb; }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <p class="meta">Generated {{ now()->format('d M Y H:i') }} &middot; {{ $rows->count() }} rows</p>
    <table>
        <thead>
            <tr>
                @foreach($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">No rows found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
