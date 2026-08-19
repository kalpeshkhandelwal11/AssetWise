@php
    $currentRoute = request()->route()?->getName() ?? '';
    $navActive = fn(string $prefix): string => str_starts_with($currentRoute, $prefix)
        ? 'bg-indigo-600 text-white'
        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white';
    $navGroupActive = function(array $prefixes) use ($currentRoute): bool {
        foreach ($prefixes as $p) {
            if (str_starts_with($currentRoute, $p)) return true;
        }
        return false;
    };

    // Pending-approval badge. Computed inline (same style as app.blade.php's unread-notification
    // count — this codebase has no View Composers) and only for users who can actually approve.
    $approvalCount = auth()->user()?->can('workflow.approve')
        ? app(\App\Services\WorkflowService::class)->pendingFor(auth()->user())->count()
        : 0;
@endphp

{{-- Dashboard --}}
<a href="{{ route('dashboard') }}"
   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ $navActive('dashboard') }}">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/>
    </svg>
    <span x-show="!sidebarCollapsed" class="truncate">Dashboard</span>
</a>

{{-- ASSETS group --}}
@canany(['assets.view', 'assets.create', 'category_fields.manage'])
<div x-data="{ open: {{ $navGroupActive(['assets.', 'admin.categories.']) ? 'true' : 'false' }} }">
    <button @click="open = !open"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
        </svg>
        <span x-show="!sidebarCollapsed" class="flex-1 truncate text-left">Assets</span>
        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="open" x-collapse class="pl-8 mt-1 space-y-1">
        @can('assets.view')
        <a href="{{ route('assets.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('assets.index') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">All Assets</span>
        </a>
        @endcan
        @can('assets.create')
        <a href="{{ route('assets.create') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('assets.create') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Add Asset</span>
        </a>
        @endcan
        @can('assets.bulk')
        <a href="{{ route('assets.import.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('assets.import') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Bulk Import</span>
        </a>
        @endcan
        @can('assets.view')
        <a href="{{ route('admin.categories.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.categories') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Categories</span>
        </a>
        @endcan
    </div>
</div>
@endcanany

{{-- TAGS / QR (M05) --}}
@canany(['tags.view', 'tags.generate', 'tags.print', 'settings.manage'])
<div x-data="{ open: {{ $navGroupActive(['admin.tags.', 'admin.settings.tags']) ? 'true' : 'false' }} }">
    <button @click="open = !open"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0a8 8 0 11-16 0 8 8 0 0116 0z"/>
        </svg>
        <span x-show="!sidebarCollapsed" class="flex-1 truncate text-left">QR / Tags</span>
        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="open" x-collapse class="pl-8 mt-1 space-y-1">
        @can('tags.view')
        {{-- M15 — the first scan entry point in the UI; before this a scan could only start
             from the phone's native camera app hitting a printed label. --}}
        <a href="{{ route('scan.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('scan.index') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Scan</span>
        </a>
        <a href="{{ route('admin.tags.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.tags.index') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Tag Pool</span>
        </a>
        @endcan
        @can('tags.generate')
        <a href="{{ route('admin.tags.batches.create') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.tags.batches') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Generate Tags</span>
        </a>
        @endcan
        @can('tags.print')
        <a href="{{ route('admin.tags.print.pdf') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.tags.print') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Print Labels</span>
        </a>
        @endcan
        @can('settings.manage')
        <a href="{{ route('admin.settings.tags.edit') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.settings.tags') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Tag Settings</span>
        </a>
        @endcan
    </div>
</div>
@endcanany

@if(false) {{-- PHASE 2/3 nav hidden for phase-1 launch — delete this @if and its matching @endif (before the Divider) to restore --}}
{{-- APPROVALS --}}
@can('workflow.approve')
<a href="{{ route('approvals.index') }}"
   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ $navActive('approvals.') }}">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
    </svg>
    <span x-show="!sidebarCollapsed" class="flex-1 truncate">Approvals</span>
    @if($approvalCount > 0)
        <span x-show="!sidebarCollapsed" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white">{{ $approvalCount > 9 ? '9+' : $approvalCount }}</span>
    @endif
</a>
@endcan

{{-- MOVEMENT --}}
@canany(['movement.assign', 'movement.transfer', 'movement.verify'])
<div x-data="{ open: {{ $navGroupActive(['movement.']) ? 'true' : 'false' }} }">
    <button @click="open = !open"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
        </svg>
        <span x-show="!sidebarCollapsed" class="flex-1 truncate text-left">Movement</span>
        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="open" x-collapse class="pl-8 mt-1 space-y-1">
        <a href="{{ route('movements.create') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">New Movement</span>
        </a>
        <a href="{{ route('movements.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Movement History</span>
        </a>
        <a href="#" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Kits & Bundles</span>
        </a>
    </div>
</div>
@endcanany

{{-- AUDIT --}}
@canany(['audit.manage', 'audit.verify'])
<div x-data="{ open: {{ $navGroupActive(['audits.']) ? 'true' : 'false' }} }">
    <button @click="open = !open"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </svg>
        <span x-show="!sidebarCollapsed" class="flex-1 truncate text-left">Audit</span>
        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="open" x-collapse class="pl-8 mt-1 space-y-1">
        @can('audit.manage')
        <a href="{{ route('audits.campaigns.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('audits.campaigns.index') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Campaigns</span>
        </a>
        @endcan
        @can('audit.verify')
        <a href="{{ route('audits.verify') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('audits.verify') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Verify Assets</span>
        </a>
        @endcan
    </div>
</div>
@endcanany

{{-- MAINTENANCE --}}
@can('maintenance.manage')
<div x-data="{ open: {{ $navGroupActive(['maintenance.', 'amc.', 'warranty.']) ? 'true' : 'false' }} }">
    <button @click="open = !open"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span x-show="!sidebarCollapsed" class="flex-1 truncate text-left">Maintenance</span>
        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="open" x-collapse class="pl-8 mt-1 space-y-1">
        <a href="{{ route('maintenance.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('maintenance.index') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Records</span>
        </a>
        <a href="{{ route('amc.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('amc.index') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">AMC Contracts</span>
        </a>
        <a href="{{ route('warranty.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('warranty.index') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Warranty</span>
        </a>
    </div>
</div>
@endcan

{{-- DISPOSAL --}}
@canany(['disposal.request', 'disposal.approve'])
<div x-data="{ open: {{ $navGroupActive(['disposal.']) ? 'true' : 'false' }} }">
    <button @click="open = !open"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        <span x-show="!sidebarCollapsed" class="flex-1 truncate text-left">Disposal</span>
        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="open" x-collapse class="pl-8 mt-1 space-y-1">
        <a href="{{ route('disposals.create') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Requests</span>
        </a>
        <a href="{{ route('disposals.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">History</span>
        </a>
    </div>
</div>
@endcanany

@endif {{-- end PHASE 2/3 nav hidden block --}}

{{-- REPORTS --}}
@can('reports.view')
<a href="{{ route('reports.index') }}"
   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ $navActive('reports.') }}">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    <span x-show="!sidebarCollapsed" class="truncate">Reports</span>
</a>
@endcan

{{-- Divider --}}
<div class="my-2 border-t border-gray-200 dark:border-gray-700/40"></div>

{{-- ADMINISTRATION --}}
@canany(['users.view', 'roles.manage', 'masters.manage', 'masters.view', 'companies.manage', 'employees.manage', 'workflow.manage', 'login_history.view', 'activity_log.view'])
<div x-data="{ open: {{ $navGroupActive(['admin.', 'users.', 'roles.', 'masters.']) ? 'true' : 'false' }} }">
    <button @click="open = !open"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 4a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
        </svg>
        <span x-show="!sidebarCollapsed" class="flex-1 truncate text-left">Administration</span>
        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="open" x-collapse class="pl-8 mt-1 space-y-1">
        @can('users.view')
        <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.users') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Users</span>
        </a>
        @endcan
        @can('roles.manage')
        <a href="{{ route('admin.roles.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.roles') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Roles & Permissions</span>
        </a>
        @endcan
        @can('login_history.view')
        <a href="{{ route('admin.login-history.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.login-history') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Login History</span>
        </a>
        @endcan
        @canany(['masters.manage', 'masters.view'])
        <a href="{{ route('admin.masters.landing') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.masters') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Shared Masters</span>
        </a>
        <a href="{{ route('admin.locations.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.locations') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Locations</span>
        </a>
        @endcanany
        @can('companies.manage')
        <a href="{{ route('admin.companies.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.companies') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Companies</span>
        </a>
        @endcan
        @can('employees.manage')
        <a href="{{ route('admin.employees.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.employees') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Employees</span>
        </a>
        @endcan
        @can('settings.manage')
        <a href="{{ route('admin.settings.asset-naming.edit') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.settings.asset-naming') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Asset Naming</span>
        </a>
        @endcan
        @if(false) {{-- PHASE 2 admin links (Depreciation Methods, Workflow Config) hidden for phase-1 launch — delete this @if and its matching @endif to restore --}}
        @can('depreciation.manage')
        <a href="{{ route('admin.depreciation-methods.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.depreciation-methods') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Depreciation Methods</span>
        </a>
        @endcan
        @can('workflow.manage')
        <a href="{{ route('admin.workflows.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.workflows') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Workflow Config</span>
        </a>
        @endcan
        @endif {{-- end PHASE 2 admin links hidden --}}
        @can('activity_log.view')
        <a href="{{ route('admin.activity-log.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ $navActive('admin.activity-log') }} hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors">
            <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
            <span x-show="!sidebarCollapsed">Activity Log</span>
        </a>
        @endcan
    </div>
</div>
@endcanany
