<x-app-layout>
    @section('page-title', 'Companies')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Companies</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage company records that own assets</p>
        </div>
        <a href="{{ route('admin.companies.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Company
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 mb-5">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or code…"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
        <select name="status" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="active"   @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Filter</button>
        <a href="{{ route('admin.companies.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-lg border border-gray-300 dark:border-gray-700 transition-colors">Clear</a>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($companies as $company)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $company->name }}</p>
                                @if($company->city || $company->country)
                                    <p class="text-xs text-gray-400">{{ collect([$company->city, $company->country])->filter()->join(', ') }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $company->code }}</td>
                            <td class="px-4 py-3">
                                @if($company->contact_name)
                                    <p class="text-gray-700 dark:text-gray-300">{{ $company->contact_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $company->contact_email }}</p>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($company->is_active)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.companies.edit', $company) }}"
                                       class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                       title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.companies.toggle', $company) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="p-1.5 text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                title="{{ $company->is_active ? 'Deactivate' : 'Activate' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                @if($company->is_active)
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                @endif
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">No companies found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($companies->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $companies->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
