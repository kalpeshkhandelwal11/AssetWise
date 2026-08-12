<x-app-layout>
    @section('page-title', 'Kit Assignment')

    <div class="max-w-2xl space-y-6">
        <x-breadcrumb :items="[
            ['label' => 'Kit Assignments', 'url' => route('kit-assignments.index')],
            ['label' => $assignment->kit?->name ?? 'Ad-hoc bundle'],
        ]" class="mb-4" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                @php $c = match($assignment->status) { 'completed' => 'green', 'rejected' => 'red', default => 'amber' }; @endphp
                <x-status-badge :color="$c" :label="ucwords(str_replace('_', ' ', $assignment->status))" />
                @if($assignment->approvalRequest)
                    <a href="{{ route('approvals.show', $assignment->approvalRequest) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">View approval progress</a>
                @endif
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Direction</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ ucfirst($assignment->direction) }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Movement Type</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $assignment->movementType?->name }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Approval Mode</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $assignment->approval_mode === 'per_asset' ? 'Per asset' : 'Single' }}</dd></div>
                <div><dt class="text-gray-400 text-xs uppercase tracking-wide">Requested By</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $assignment->requestedBy?->name }}</dd></div>
                @if($assignment->toCustodian)<div><dt class="text-gray-400 text-xs uppercase tracking-wide">To Custodian</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $assignment->toCustodian->name }}</dd></div>@endif
                @if($assignment->toCompany)<div><dt class="text-gray-400 text-xs uppercase tracking-wide">To Company</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $assignment->toCompany->name }}</dd></div>@endif
                @if($assignment->toLocation)<div><dt class="text-gray-400 text-xs uppercase tracking-wide">To Location</dt><dd class="text-gray-900 dark:text-gray-100 mt-0.5">{{ $assignment->toLocation->name }}</dd></div>@endif
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Assets ({{ $assignment->movements->count() }})</p>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($assignment->movements as $movement)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <a href="{{ route('assets.show', $movement->asset_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $movement->asset?->name ?? 'Asset #'.$movement->asset_id }}</a>
                        @php $mc = match($movement->status) { 'completed' => 'green', 'rejected' => 'red', default => 'amber' }; @endphp
                        <x-status-badge :color="$mc" :label="ucwords(str_replace('_', ' ', $movement->status))" />
                    </div>
                @endforeach
            </div>
        </div>

        @can('kits.assign')
            @if($assignment->direction === 'out' && $assignment->status === 'completed')
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Return Kit</p>
                    <p class="text-xs text-gray-400 mb-4">Moves every asset in this assignment back (RETURN), in one approval.</p>
                    <form method="POST" action="{{ route('kit-assignments.return', $assignment) }}">
                        @csrf
                        <button class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Return Kit</button>
                    </form>
                </div>
            @endif
        @endcan
    </div>
</x-app-layout>
