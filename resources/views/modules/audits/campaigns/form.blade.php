@php
    $scope = old('scope', $campaign->scope ?? []);
    $selectedAuditors = old('auditor_ids', $campaign->exists ? $campaign->auditors->pluck('id')->all() : []);
@endphp
<x-app-layout>
    @section('page-title', $campaign->exists ? 'Edit Audit Campaign' : 'New Audit Campaign')

    <div class="max-w-3xl">
        <x-breadcrumb :items="[
            ['label' => 'Audit Campaigns', 'url' => route('audits.campaigns.index')],
            ['label' => $campaign->exists ? 'Edit Campaign' : 'New Campaign'],
        ]" class="mb-4" />

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <form method="POST" action="{{ $campaign->exists ? route('audits.campaigns.update', $campaign) : route('audits.campaigns.store') }}" class="space-y-6">
                @csrf
                @if($campaign->exists) @method('PUT') @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" value="Campaign Name" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $campaign->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="audit_type_id" value="Audit Type" />
                        <select id="audit_type_id" name="audit_type_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="">— Select —</option>
                            @foreach($auditTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('audit_type_id', $campaign->audit_type_id) == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('audit_type_id')" class="mt-1" />
                    </div>
                    <div></div>
                    <div>
                        <x-input-label for="start_date" value="Start Date" />
                        <x-text-input id="start_date" type="date" name="start_date" class="mt-1 block w-full" :value="old('start_date', optional($campaign->start_date)->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="End Date" />
                        <x-text-input id="end_date" type="date" name="end_date" class="mt-1 block w-full" :value="old('end_date', optional($campaign->end_date)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('description', $campaign->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <div class="border-t border-gray-100 dark:border-gray-700 pt-5">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Scope</p>
                    <p class="text-xs text-gray-400 mb-4">Assets matching every filter set below are snapshotted into the campaign on activation. Frozen once active.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="scope_company_id" value="Company" />
                            <x-searchable-select id="scope_company_id" name="scope[company_id]" data-placeholder="Any">
                                <option value="">Any</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected(($scope['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </x-searchable-select>
                        </div>
                        <div>
                            <x-input-label for="scope_category_id" value="Category" />
                            <x-searchable-select id="scope_category_id" name="scope[category_id]" data-placeholder="Any">
                                <option value="">Any</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected(($scope['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </x-searchable-select>
                        </div>
                        <div>
                            <x-input-label for="scope_asset_type_id" value="Asset Type" />
                            <select id="scope_asset_type_id" name="scope[asset_type_id]"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">Any</option>
                                @foreach($assetTypes as $type)
                                    <option value="{{ $type->id }}" @selected(($scope['asset_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="scope_status_id" value="Asset Status" />
                            <select id="scope_status_id" name="scope[status_id]"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">Any</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}" @selected(($scope['status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="scope_location_id" value="Location" />
                            <x-searchable-select id="scope_location_id" name="scope[location_id]" data-placeholder="Any">
                                <option value="">Any</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" @selected(($scope['location_id'] ?? null) == $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </x-searchable-select>
                        </div>
                        <div>
                            <x-input-label for="scope_department_id" value="Department" />
                            <x-searchable-select id="scope_department_id" name="scope[department_id]" data-placeholder="Any">
                                <option value="">Any</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" @selected(($scope['department_id'] ?? null) == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </x-searchable-select>
                        </div>
                        <div>
                            <x-input-label for="scope_branch_id" value="Branch" />
                            <x-searchable-select id="scope_branch_id" name="scope[branch_id]" data-placeholder="Any">
                                <option value="">Any</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(($scope['branch_id'] ?? null) == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </x-searchable-select>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('scope')" class="mt-2" />
                </div>

                <div class="border-t border-gray-100 dark:border-gray-700 pt-5">
                    <x-input-label for="auditor_ids" value="Assigned Auditors" />
                    <p class="text-xs text-gray-400 mb-1">Only these users will see this campaign on the verify worklist. Type to search; click to add multiple.</p>
                    <x-searchable-select id="auditor_ids" name="auditor_ids[]" multiple data-placeholder="Select auditors…">
                        @foreach($auditors as $auditor)
                            <option value="{{ $auditor->id }}" @selected(in_array($auditor->id, $selectedAuditors))>{{ $auditor->name }}</option>
                        @endforeach
                    </x-searchable-select>
                    <x-input-error :messages="$errors->get('auditor_ids')" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <a href="{{ $campaign->exists ? route('audits.campaigns.show', $campaign) : route('audits.campaigns.index') }}"
                       class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">
                        Cancel
                    </a>
                    <x-primary-button>{{ $campaign->exists ? 'Update Campaign' : 'Create Campaign' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
