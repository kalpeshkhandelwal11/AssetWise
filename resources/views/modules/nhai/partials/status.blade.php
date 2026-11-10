@php
    [$label, $color] = [
        'draft'            => ['Draft', 'gray'],
        'pending_approval' => ['Pending approval', 'yellow'],
        'approved'         => ['Approved', 'green'],
        'rejected'         => ['Rejected', 'red'],
        'apply_failed'     => ['Apply failed', 'red'],
    ][$status] ?? [ucfirst((string) $status), 'gray'];
@endphp
<x-status-badge :color="$color" :label="$label" :dot="false" />
