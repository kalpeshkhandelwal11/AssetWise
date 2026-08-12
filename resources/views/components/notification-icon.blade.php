@props(['icon' => 'bell'])

@php
// Curated subset of heroicons outline paths already used elsewhere in this app (see
// dashboard.blade.php, layouts/app.blade.php's bell button) plus a few standard additions —
// kept to a fixed small set rather than one entry per catalog icon name, since this is
// decorative only.
$paths = [
    'clock'              => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'check-circle'       => 'M5 13l4 4L19 7',
    'x-circle'           => 'M6 18L18 6M6 6l12 12',
    'arrow-up-circle'    => 'M5 15l7-7 7 7',
    'arrow-right-circle' => 'M9 5l7 7-7 7',
    'clipboard-check'    => 'M5 13l4 4L19 7',
    'trash'              => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
    'exclamation-triangle' => 'M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.75-2.97l-6.93-12a2 2 0 00-3.5 0l-6.93 12A2 2 0 005.07 19z',
    'download'           => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
    'upload'             => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5',
    'bell'               => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
];
$path = $paths[$icon] ?? $paths['bell'];
@endphp

<svg {{ $attributes->merge(['class' => 'w-4 h-4']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"/>
</svg>
