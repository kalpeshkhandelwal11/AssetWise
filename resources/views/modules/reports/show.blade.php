<x-app-layout>
    @section('page-title', $label)

    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => $label],
    ]" class="mb-4" />

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $label }}</h1>
        </div>
        @can('reports.export')
        <div class="flex gap-2">
            <form method="POST" action="{{ route('reports.export', $type) }}">
                @csrf
                <input type="hidden" name="format" value="xlsx">
                @foreach($filters as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <button type="submit" class="px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">Export Excel</button>
            </form>
            <form method="POST" action="{{ route('reports.export', $type) }}">
                @csrf
                <input type="hidden" name="format" value="pdf">
                @foreach($filters as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <button type="submit" class="px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">Export PDF</button>
            </form>
        </div>
        @endcan
    </div>

    <x-filter-bar :clear="route('reports.show', $type)" :auto="false">
        @switch($type)
            @case('asset_register')
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="category_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="status_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->id }}" @selected(($filters['status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search asset name or tag…"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @break

            @case('movement')
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="movement_type_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Movement Types</option>
                    @foreach($movementTypes as $movementType)
                        <option value="{{ $movementType->id }}" @selected(($filters['movement_type_id'] ?? null) == $movementType->id)>{{ $movementType->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    @foreach(['pending_approval' => 'Pending Approval', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $value => $optionLabel)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search asset name or tag…"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @break

            @case('intercompany_transfer')
                <select name="from_company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">From: Any Company</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['from_company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="to_company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">To: Any Company</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['to_company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search asset name or tag…"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @break

            @case('disposal')
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="disposal_type_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Types</option>
                    @foreach($disposalTypes as $disposalType)
                        <option value="{{ $disposalType->id }}" @selected(($filters['disposal_type_id'] ?? null) == $disposalType->id)>{{ $disposalType->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    @foreach(['pending_approval' => 'Pending Approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'written_off' => 'Written Off', 'scrapped' => 'Scrapped'] as $value => $optionLabel)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @break

            @case('maintenance')
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="maintenance_type_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Types</option>
                    @foreach($maintenanceTypes as $maintenanceType)
                        <option value="{{ $maintenanceType->id }}" @selected(($filters['maintenance_type_id'] ?? null) == $maintenanceType->id)>{{ $maintenanceType->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    @foreach(['scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $optionLabel)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
                <select name="is_capitalized" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">Capitalized: Any</option>
                    <option value="1" @selected(($filters['is_capitalized'] ?? null) === '1')>Capitalized Only</option>
                    <option value="0" @selected(($filters['is_capitalized'] ?? null) === '0')>Not Capitalized</option>
                </select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" placeholder="Performed from" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" placeholder="Performed to" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search asset name or tag…"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @break

            @case('amc_warranty')
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="kind" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Kinds</option>
                    <option value="amc" @selected(($filters['kind'] ?? null) === 'amc')>AMC</option>
                    <option value="warranty" @selected(($filters['kind'] ?? null) === 'warranty')>Warranty</option>
                </select>
                <select name="expiry_status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">Any Expiry Status</option>
                    <option value="expired" @selected(($filters['expiry_status'] ?? null) === 'expired')>Expired</option>
                    <option value="expiring" @selected(($filters['expiry_status'] ?? null) === 'expiring')>Expiring Soon</option>
                    <option value="active" @selected(($filters['expiry_status'] ?? null) === 'active')>Active</option>
                </select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" placeholder="Ends from" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" placeholder="Ends to" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search asset name or tag…"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @break

            @case('aging')
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="category_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="status_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->id }}" @selected(($filters['status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
                @break

            @case('utilization')
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                @break

            @case('audit_compliance')
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">Any Status Involved</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->id }}" @selected(($filters['status'] ?? null) == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
                <select name="changed_by" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">Anyone</option>
                    @foreach($users as $reportUser)
                        <option value="{{ $reportUser->id }}" @selected(($filters['changed_by'] ?? null) == $reportUser->id)>{{ $reportUser->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @break

            @case('audit_campaign')
                <select name="campaign_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Campaigns</option>
                    @foreach($auditCampaigns as $auditCampaign)
                        <option value="{{ $auditCampaign->id }}" @selected(($filters['campaign_id'] ?? null) == $auditCampaign->id)>{{ $auditCampaign->name }}</option>
                    @endforeach
                </select>
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    @foreach(['pending' => 'Pending', 'verified' => 'Verified', 'missing' => 'Missing', 'damaged' => 'Damaged'] as $value => $optionLabel)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search asset name or tag…"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @break

            @case('depreciation_schedule')
                <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <select name="category_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                    <option value="">All Lines</option>
                    @foreach(['scheduled' => 'Scheduled', 'posted' => 'Posted'] as $value => $optionLabel)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search asset name or tag…"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
                @break
        @endswitch
    </x-filter-bar>

    @include("modules.reports.types.{$type}", ['rows' => $rows])
</x-app-layout>
