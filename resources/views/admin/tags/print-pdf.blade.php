<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Asset Tags</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        .grid { width: 100%; }
        .cell {
            display: inline-block;
            width: 30%;
            text-align: center;
            margin: 1.5%;
            padding: 8px;
            border: 1px solid #ccc;
            vertical-align: top;
        }
        .cell img { width: 100px; height: 100px; }
        .tag-number { margin-top: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Asset Tags</h2>
    <div class="grid">
        @foreach($tags as $entry)
            <div class="cell">
                <img src="data:{{ $entry['mime'] }};base64,{{ $entry['image'] }}" alt="{{ $entry['tag']->tag_number }}">
                <div class="tag-number">{{ $entry['tag']->tag_number }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
