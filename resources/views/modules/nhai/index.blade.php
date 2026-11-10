<x-app-layout>
    @section('page-title', 'NHAI / Toll Plazas')

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">NHAI / Toll Plazas</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Assets received from NHAI, and plaza takeovers / handovers.</p>
        </div>
        <div class="flex flex-wrap gap-2">
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
        @foreach(['assets' => 'Assets received from NHAI', 'takeovers' => 'Takeovers', 'handovers' => 'Handovers'] as $key => $label)
            <a href="{{ route('nhai.index', ['tab' => $key]) }}"
               class="px-4 py-2 -mb-px border-b-2 {{ $tab === $key ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($tab === 'assets')
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
    @else
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
