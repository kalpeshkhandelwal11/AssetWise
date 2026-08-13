<x-app-layout>
    @section('page-title', 'Assets')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Assets</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">All assets across companies</p>
        </div>
        @can('assets.create')
        <a href="{{ route('assets.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Asset
        </a>
        @endcan
    </div>

    @php $canBulkMove = auth()->user()->can('assets.bulk') && auth()->user()->canAny(['movement.assign', 'movement.transfer']); @endphp
    <div x-data="{ selected: [] }">

    {{-- Filters --}}
    <x-filter-bar :clear="route('assets.index')">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search tag, name, serial…"
               class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">

        <select name="company_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Companies</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>

        <select name="category_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>

        <select name="asset_type_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Types</option>
            @foreach($types as $type)
                <option value="{{ $type->id }}" @selected(request('asset_type_id') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>

        <select name="status_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status->id }}" @selected(request('status_id') == $status->id)>{{ $status->name }}</option>
            @endforeach
        </select>

        <select name="sort" onchange="this.form.submit()" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            <option value="updated"  @selected($sort === 'updated')>Last updated</option>
            <option value="name"     @selected($sort === 'name')>Name (A–Z)</option>
            <option value="asset_id" @selected($sort === 'asset_id')>Asset ID</option>
            <option value="created"  @selected($sort === 'created')>Recently added</option>
        </select>

        <select name="per_page" onchange="this.form.submit()" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:ring-indigo-500">
            @foreach([20, 50, 100] as $n)
                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page</option>
            @endforeach
        </select>

        @can('assets.delete')
        <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 px-2">
            <input type="checkbox" name="show_deleted" value="1" @checked(request('show_deleted')) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            Show deleted
        </label>
        @endcan
    </x-filter-bar>

    <x-data-table :paginator="$assets">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        @if($canBulkMove)
                        <th class="px-4 py-3 w-8">
                            <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                   @change="selected = $event.target.checked ? @js($assets->pluck('id')) : []">
                        </th>
                        @endif
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Location</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Custodian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($assets as $asset)
                        <tr @click="window.location='{{ route('assets.show', $asset) }}'"
                            class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors {{ $asset->trashed() ? 'opacity-60' : '' }}">
                            @if($canBulkMove)
                            <td class="px-4 py-3" @click.stop>
                                <input type="checkbox" value="{{ $asset->id }}" x-model.number="selected" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            @endif
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $asset->name }}</p>
                                @if($asset->serial_number)
                                    <p class="text-xs text-gray-400">SN: {{ $asset->serial_number }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $asset->asset_tag ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $asset->company?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $asset->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($asset->status)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                          style="background-color: {{ $asset->status->color }}20; color: {{ $asset->status->color }}">
                                        {{ $asset->status->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                @php $loc = collect([$asset->location?->name, $asset->building?->name, $asset->room?->name])->filter(); @endphp
                                {{ $loc->isNotEmpty() ? $loc->join(' · ') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $asset->custodian?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canBulkMove ? 8 : 7 }}" class="px-4 py-12 text-center text-sm text-gray-400">No assets found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
    </x-data-table>

    @if($canBulkMove)
        {{-- No x-transition here on purpose: Alpine's JS transition engine applies a stale
             state to this fixed-position element (verified in-browser — the bar showed the
             previous value of selected.length, so it stayed hidden when assets were picked).
             Plain x-show toggles reliably; the bar is a utility affordance, not an animation. --}}
        <div x-show="selected.length > 0" x-cloak
             class="fixed bottom-6 left-1/2 -translate-x-1/2 flex items-center gap-4 px-5 py-3 rounded-xl bg-gray-900 dark:bg-gray-800 text-white shadow-2xl border border-gray-700 z-40">
            <span class="text-sm"><span x-text="selected.length"></span> selected</span>
            <a :href="'{{ route('movements.bulk.create') }}?' + selected.map(id => 'asset_ids[]=' + id).join('&')"
               class="px-3 py-1.5 text-sm font-medium bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                Move Selected
            </a>
            <button type="button" @click="selected = []" class="text-gray-400 hover:text-white text-sm">Clear</button>
        </div>
    @endif
    </div>
</x-app-layout>
