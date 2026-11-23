<x-app-layout>
    @section('page-title', 'Handover / Takeover')

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Handover / Takeover</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Toll plazas that work with NHAI, what each holds, and plaza takeovers / handovers.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($tab === 'plazas')
                @can('masters.manage')
                    {{-- Opens the shared location popup as a new NHAI toll plaza; saving returns here. --}}
                    <button type="button" x-data
                            @click="$dispatch('open-location-modal', { is_toll_plaza: true, is_nhai_plaza: true, return_to: 'nhai' })"
                            class="px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700">+ Add toll plaza</button>
                @endcan
            @endif
            @can('takeover.manage')
                <a href="{{ route('nhai.takeovers.create', ['kind' => 'nhai']) }}" class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Takeover from NHAI</a>
                <a href="{{ route('nhai.takeovers.create', ['kind' => 'purchase']) }}" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Purchase at takeover</a>
            @endcan
            @can('handover.manage')
                <a href="{{ route('nhai.handovers.create') }}" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Plaza handover</a>
            @endcan
        </div>
    </div>

    <div class="flex gap-1 mb-4 border-b border-gray-200 dark:border-gray-700 text-sm">
        @foreach(['plazas' => 'Toll Plazas', 'takeovers' => 'Takeovers', 'handovers' => 'Handovers'] as $key => $label)
            @php $on = $tab === $key || ($key === 'plazas' && $tab === 'assets'); @endphp
            <a href="{{ route('nhai.index', ['tab' => $key]) }}"
               class="px-4 py-2 -mb-px border-b-2 {{ $on ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($tab === 'plazas')
        @php $totals = $dashboard['totals']; @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach([['NHAI toll plazas', $totals['plazas'], 'indigo'], ['NHAI assets', $totals['nhai'], 'amber'], ['Our assets at these plazas', $totals['ours'], 'green'], ['Not working (NHAI)', $totals['not_working'], 'red']] as [$label, $value, $color])
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-{{ $color }}-600 dark:text-{{ $color }}-400">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>

        <x-filter-bar :clear="route('nhai.index', ['tab' => 'plazas'])">
            <input type="hidden" name="tab" value="plazas">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Plaza name or code…"
                   class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
        </x-filter-bar>

        <div class="space-y-3">
            @forelse($dashboard['rows'] as $row)
                @php $plazaRow = $row['plaza']; @endphp
                <details class="group bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <summary class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 cursor-pointer list-none">
                        <div class="flex items-center gap-3 min-w-0">
                            <svg class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-90 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $plazaRow->name }}</p>
                                <p class="text-xs text-gray-400">{{ $plazaRow->code }}@if($plazaRow->chainage) · Chainage {{ $plazaRow->chainage }}@endif</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span title="Assets owned by NHAI at this plaza"><span class="font-semibold text-amber-600 dark:text-amber-400">{{ $row['nhai'] }}</span> <span class="text-gray-500">NHAI</span></span>
                            <span title="Our own assets at this plaza"><span class="font-semibold text-green-600 dark:text-green-400">{{ $row['ours'] }}</span> <span class="text-gray-500">ours</span></span>
                            @if($row['not_working'] > 0)
                                <span class="text-xs text-red-600 dark:text-red-400">{{ $row['not_working'] }} not working</span>
                            @endif
                        </div>
                    </summary>

                    <div class="border-t border-gray-100 dark:border-gray-700 px-5 py-4 grid grid-cols-1 lg:grid-cols-3 gap-6 text-sm">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Plaza</p>
                            <dl class="space-y-1 text-gray-700 dark:text-gray-300">
                                <div><dt class="inline text-gray-400">Chainage:</dt> <dd class="inline">{{ $plazaRow->chainage ?: '—' }}</dd></div>
                                <div><dt class="inline text-gray-400">Manager:</dt> <dd class="inline">{{ $plazaRow->manager?->name ?? '—' }}</dd></div>
                                <div><dt class="inline text-gray-400">Address:</dt> <dd class="inline">{{ $plazaRow->address ?: '—' }}</dd></div>
                            </dl>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mt-4 mb-2">Takeover / handover</p>
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-gray-400">Takeover:</span>
                                    @if($row['takeover'])
                                        <a href="{{ route('nhai.takeovers.show', $row['takeover']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row['takeover']->reference_no }}</a>
                                        <span class="text-gray-400">{{ $row['takeover']->takeover_date?->format('d M Y') }}</span>
                                        @include('modules.nhai.partials.status', ['status' => $row['takeover']->status])
                                    @else
                                        <span class="text-gray-500">none</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-gray-400">Handover:</span>
                                    @if($row['handover'])
                                        <a href="{{ route('nhai.handovers.show', $row['handover']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row['handover']->reference_no ?? ('#' . $row['handover']->id) }}</a>
                                        @include('modules.nhai.partials.status', ['status' => $row['handover']->status])
                                    @else
                                        <span class="text-gray-500">not started</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-2">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">By category</p>
                            @if($row['categories']->isEmpty())
                                <p class="text-gray-500">No live assets at this plaza. Assets returned to NHAI, handed over, sold or written off are not counted.</p>
                            @else
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-gray-400">
                                        <tr><th class="text-left font-medium py-1">Category</th><th class="text-right font-medium py-1">NHAI</th><th class="text-right font-medium py-1">Ours</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @foreach($row['categories'] as $cat)
                                            <tr>
                                                <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $cat['name'] }}</td>
                                                <td class="py-1.5 text-right font-medium text-amber-600 dark:text-amber-400">{{ $cat['nhai'] ?: '—' }}</td>
                                                <td class="py-1.5 text-right font-medium text-green-600 dark:text-green-400">{{ $cat['ours'] ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ route('nhai.index', ['tab' => 'assets', 'location_id' => $plazaRow->id]) }}"
                                   class="px-3 py-1.5 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Details</a>
                                @can('handover.manage')
                                    <a href="{{ route('nhai.handovers.create', ['location_id' => $plazaRow->id]) }}"
                                       class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Start handover</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </details>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-8 text-center text-sm text-gray-500">
                    No toll plazas working with NHAI yet.
                    @can('masters.manage') Use <strong>+ Add toll plaza</strong>, or tick "Works with NHAI" on a toll plaza under Administration → Locations. @endcan
                </div>
            @endforelse
        </div>

        <div class="mt-4 text-right text-sm">
            <a href="{{ route('nhai.index', ['tab' => 'assets']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">View all NHAI assets →</a>
        </div>

        @can('masters.manage')
            @include('admin.locations.partials.location-modal', ['managers' => $managers])
        @endcan
    @endif

    @if($tab === 'assets')
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('nhai.index', ['tab' => 'plazas']) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← Toll plazas</a>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">NHAI assets — {{ $plaza?->name ?? 'all toll plazas' }}</h2>
        </div>
        <x-filter-bar :clear="route('nhai.index')">
            <input type="hidden" name="tab" value="assets">
            <select name="location_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                <option value="">All plazas</option>
                @foreach($plazas as $plaza)
                    <option value="{{ $plaza->id }}" @selected(($filters['location_id'] ?? null) == $plaza->id)>{{ $plaza->name }}</option>
                @endforeach
            </select>
            <select name="category_id" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="condition" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                <option value="">Any condition</option>
                @foreach(\App\Models\PlazaTakeoverLine::CONDITIONS as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['condition'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Item, serial or tag…"
                   class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
        </x-filter-bar>

        <div class="flex justify-end gap-2 mb-2 text-xs">
            <a href="{{ route('nhai.export', request()->query()) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Export Excel</a>
            <a href="{{ route('nhai.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Export PDF</a>
        </div>

        <x-data-table>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-4 py-3 text-left">Item</th>
                        <th class="px-4 py-3 text-left">Make / model</th>
                        <th class="px-4 py-3 text-left">Serial</th>
                        <th class="px-4 py-3 text-left">Plaza · room / lane</th>
                        <th class="px-4 py-3 text-left">Condition</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($assets as $asset)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3">
                                <a href="{{ route('assets.show', $asset) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $asset->name }}</a>
                                <span class="block text-xs text-gray-400 font-mono">{{ $asset->asset_tag }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ collect([$asset->manufacturer, $asset->model])->filter()->join(' · ') ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ $asset->serial_number ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ collect([$asset->location?->name, $asset->room?->name])->filter()->join(' · ') }}</td>
                            <td class="px-4 py-3">
                                <x-status-badge :color="['functional' => 'green', 'partially_functional' => 'amber', 'not_functional' => 'red'][$asset->condition] ?? 'gray'"
                                                :label="\App\Models\PlazaTakeoverLine::CONDITIONS[$asset->condition] ?? '—'" :dot="false" />
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $asset->status?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">No NHAI assets yet — record a takeover from NHAI.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
        <div class="mt-4">{{ $assets->links() }}</div>
    @elseif(in_array($tab, ['takeovers', 'handovers'], true))
        @php $rows = $tab === 'takeovers' ? $takeovers : $handovers; @endphp
        <x-data-table>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-left">Plaza</th>
                        <th class="px-4 py-3 text-left">{{ $tab === 'takeovers' ? 'Type / from' : 'Company' }}</th>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Lines</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3">
                                <a href="{{ $tab === 'takeovers' ? route('nhai.takeovers.show', $row) : route('nhai.handovers.show', $row) }}"
                                   class="font-mono text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row->reference_no }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row->location?->name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                @if($tab === 'takeovers')
                                    {{ \App\Models\PlazaTakeover::KINDS[$row->kind] ?? $row->kind }} · {{ $row->fromCompany?->name }}
                                @else
                                    {{ $row->company?->name }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ ($row->takeover_date ?? $row->handover_date)?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $row->lines_count }}</td>
                            <td class="px-4 py-3">@include('modules.nhai.partials.status', ['status' => $row->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">None yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
        <div class="mt-4">{{ $rows->links() }}</div>
    @endif
</x-app-layout>
