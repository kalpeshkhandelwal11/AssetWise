@php
    $currentRoute = request()->route()?->getName() ?? '';
    $isActive = fn(string $prefix): bool => str_starts_with($currentRoute, $prefix);
    $groupActive = function(array $prefixes) use ($currentRoute): bool {
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

<x-sidebar-section>
    <x-sidebar-link :href="route('dashboard')" :active="$isActive('dashboard')">
        <x-slot:icon>
            {{-- 4-panel grid — the standard "Dashboard" glyph (four independent widgets/panels) --}}
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <rect x="3" y="3" width="8" height="8" rx="1.5" stroke-width="2"/>
                <rect x="13" y="3" width="8" height="8" rx="1.5" stroke-width="2"/>
                <rect x="3" y="13" width="8" height="8" rx="1.5" stroke-width="2"/>
                <rect x="13" y="13" width="8" height="8" rx="1.5" stroke-width="2"/>
            </svg>
        </x-slot:icon>
        Dashboard
    </x-sidebar-link>
</x-sidebar-section>

{{-- ASSET MANAGEMENT: Assets, QR/Tags --}}
@canany(['assets.view', 'assets.create', 'category_fields.manage', 'tags.view', 'tags.generate', 'tags.print', 'settings.manage'])
<x-sidebar-section label="Asset Management">
    @canany(['assets.view', 'assets.create', 'category_fields.manage'])
    <x-sidebar-group label="Assets" :active="$groupActive(['assets.', 'admin.categories.'])">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
            </svg>
        </x-slot:icon>

        @can('assets.view')
        <x-sidebar-link child :href="route('assets.index')" :active="$isActive('assets.index')">All Assets</x-sidebar-link>
        @endcan
        @can('assets.create')
        <x-sidebar-link child :href="route('assets.create')" :active="$isActive('assets.create')">Add Asset</x-sidebar-link>
        @endcan
        @can('assets.bulk')
        <x-sidebar-link child :href="route('assets.import.index')" :active="$isActive('assets.import')">Bulk Import</x-sidebar-link>
        @endcan
        @can('assets.view')
        <x-sidebar-link child :href="route('admin.categories.index')" :active="$isActive('admin.categories')">Categories</x-sidebar-link>
        @endcan
    </x-sidebar-group>
    @endcanany

    {{-- TAGS / QR (M05) --}}
    @canany(['tags.view', 'tags.generate', 'tags.print', 'settings.manage'])
    <x-sidebar-group label="QR / Tags" :active="$groupActive(['admin.tags.', 'admin.settings.tags'])">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0a8 8 0 11-16 0 8 8 0 0116 0z"/>
            </svg>
        </x-slot:icon>

        @can('tags.view')
        {{-- M15 — the first scan entry point in the UI; before this a scan could only start
             from the phone's native camera app hitting a printed label. --}}
        <x-sidebar-link child :href="route('scan.index')" :active="$isActive('scan.index')">Scan</x-sidebar-link>
        <x-sidebar-link child :href="route('admin.tags.index')" :active="$isActive('admin.tags.index')">Tag Pool</x-sidebar-link>
        @endcan
        @can('tags.generate')
        <x-sidebar-link child :href="route('admin.tags.batches.create')" :active="$isActive('admin.tags.batches')">Generate Tags</x-sidebar-link>
        @endcan
        @can('tags.print')
        <x-sidebar-link child :href="route('admin.tags.print.pdf')" :active="$isActive('admin.tags.print')">Print Labels</x-sidebar-link>
        @endcan
        @can('settings.manage')
        <x-sidebar-link child :href="route('admin.settings.tags.edit')" :active="$isActive('admin.settings.tags')">Tag Settings</x-sidebar-link>
        @endcan
    </x-sidebar-group>
    @endcanany
</x-sidebar-section>
@endcanany

{{-- WORKFLOW & REPORTS: Approvals, Reports --}}
@canany(['workflow.approve', 'reports.view'])
<x-sidebar-section label="Workflow & Reports">
    @can('workflow.approve')
    <x-sidebar-link :href="route('approvals.index')" :active="$isActive('approvals.')">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
        </x-slot:icon>
        @if($approvalCount > 0)
            <x-slot:badge>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white">{{ $approvalCount > 9 ? '9+' : $approvalCount }}</span>
            </x-slot:badge>
        @endif
        Approvals
    </x-sidebar-link>
    @endcan

    {{-- MOVEMENT --}}
    @canany(['movement.assign', 'movement.transfer', 'movement.verify'])
    <x-sidebar-group label="Movement" :active="$groupActive(['movement.'])">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
        </x-slot:icon>
        <x-sidebar-link child :href="route('movements.create')">New Movement</x-sidebar-link>
        <x-sidebar-link child :href="route('movements.index')">Movement History</x-sidebar-link>
        <x-sidebar-link child href="#">Kits & Bundles</x-sidebar-link>
    </x-sidebar-group>
    @endcanany

    {{-- AUDIT --}}
    @canany(['audit.manage', 'audit.verify'])
    <x-sidebar-group label="Audit" :active="$groupActive(['audits.'])">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
        </x-slot:icon>
        @can('audit.manage')
        <x-sidebar-link child :href="route('audits.campaigns.index')" :active="$isActive('audits.campaigns.index')">Campaigns</x-sidebar-link>
        @endcan
        @can('audit.verify')
        <x-sidebar-link child :href="route('audits.verify')" :active="$isActive('audits.verify')">Verify Assets</x-sidebar-link>
        @endcan
    </x-sidebar-group>
    @endcanany

    {{-- MAINTENANCE --}}
    @can('maintenance.manage')
    <x-sidebar-group label="Maintenance" :active="$groupActive(['maintenance.', 'amc.', 'warranty.'])">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </x-slot:icon>
        <x-sidebar-link child :href="route('maintenance.index')" :active="$isActive('maintenance.index')">Records</x-sidebar-link>
        <x-sidebar-link child :href="route('amc.index')" :active="$isActive('amc.index')">AMC Contracts</x-sidebar-link>
        <x-sidebar-link child :href="route('warranty.index')" :active="$isActive('warranty.index')">Warranty</x-sidebar-link>
    </x-sidebar-group>
    @endcan

    {{-- DISPOSAL --}}
    @canany(['disposal.request', 'disposal.approve'])
    <x-sidebar-group label="Disposal" :active="$groupActive(['disposal.'])">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </x-slot:icon>
        <x-sidebar-link child :href="route('disposals.create')">Requests</x-sidebar-link>
        <x-sidebar-link child :href="route('disposals.index')">History</x-sidebar-link>
    </x-sidebar-group>
    @endcanany

    {{-- REPORTS --}}
    @can('reports.view')
    <x-sidebar-link :href="route('reports.index')" :active="$isActive('reports.')">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
        </x-slot:icon>
        Reports
    </x-sidebar-link>
    @endcan
</x-sidebar-section>
@endcanany

{{-- ADMINISTRATION --}}
@canany(['users.view', 'roles.manage', 'masters.manage', 'masters.view', 'companies.manage', 'employees.manage', 'workflow.manage', 'login_history.view', 'activity_log.view'])
<x-sidebar-section label="Administration">
    <x-sidebar-group label="Administration" :active="$groupActive(['admin.', 'users.', 'roles.', 'masters.'])">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 4a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
            </svg>
        </x-slot:icon>

        @can('users.view')
        <x-sidebar-link child :href="route('admin.users.index')" :active="$isActive('admin.users')">Users</x-sidebar-link>
        @endcan
        @can('roles.manage')
        <x-sidebar-link child :href="route('admin.roles.index')" :active="$isActive('admin.roles')">Roles & Permissions</x-sidebar-link>
        @endcan
        @can('login_history.view')
        <x-sidebar-link child :href="route('admin.login-history.index')" :active="$isActive('admin.login-history')">Login History</x-sidebar-link>
        @endcan
        @canany(['masters.manage', 'masters.view'])
        <x-sidebar-link child :href="route('admin.masters.landing')" :active="$isActive('admin.masters')">Shared Masters</x-sidebar-link>
        <x-sidebar-link child :href="route('admin.locations.index')" :active="$isActive('admin.locations')">Locations</x-sidebar-link>
        @endcanany
        @can('companies.manage')
        <x-sidebar-link child :href="route('admin.companies.index')" :active="$isActive('admin.companies')">Companies</x-sidebar-link>
        @endcan
        @can('employees.manage')
        <x-sidebar-link child :href="route('admin.employees.index')" :active="$isActive('admin.employees')">Employees</x-sidebar-link>
        @endcan
        @can('settings.manage')
        <x-sidebar-link child :href="route('admin.settings.asset-naming.edit')" :active="$isActive('admin.settings.asset-naming')">Asset Naming</x-sidebar-link>
        @endcan
        @can('depreciation.manage')
        <x-sidebar-link child :href="route('admin.depreciation-methods.index')" :active="$isActive('admin.depreciation-methods')">Depreciation Methods</x-sidebar-link>
        @endcan
        @can('workflow.manage')
        <x-sidebar-link child :href="route('admin.workflows.index')" :active="$isActive('admin.workflows')">Workflow Config</x-sidebar-link>
        @endcan
        @can('activity_log.view')
        <x-sidebar-link child :href="route('admin.activity-log.index')" :active="$isActive('admin.activity-log')">Activity Log</x-sidebar-link>
        @endcan
    </x-sidebar-group>
</x-sidebar-section>
@endcanany
