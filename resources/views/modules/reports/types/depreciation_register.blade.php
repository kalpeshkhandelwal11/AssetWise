@php
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
@endphp
<x-data-table>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
            <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                <th class="px-3 py-3 text-left">SI</th>
                <th class="px-3 py-3 text-left">Asset</th>
                <th class="px-3 py-3 text-left">Purchase</th>
                <th class="px-3 py-3 text-left">From → To</th>
                <th class="px-3 py-3 text-right">Cost</th>
                <th class="px-3 py-3 text-right">Residual</th>
                <th class="px-3 py-3 text-right">Life</th>
                <th class="px-3 py-3 text-right">Rate</th>
                <th class="px-3 py-3 text-right">Days</th>
                <th class="px-3 py-3 text-right">Opening WDV</th>
                <th class="px-3 py-3 text-right">Depreciation</th>
                <th class="px-3 py-3 text-right">Closing WDV</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @php $currentClass = null; @endphp
            @forelse($rows as $row)
                @if($row['kind'] === 'asset')
                    @if($row['class'] !== $currentClass)
                        @php $currentClass = $row['class']; @endphp
                        <tr class="bg-gray-50/60 dark:bg-gray-900/30">
                            <td colspan="12" class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide">{{ $row['class'] }}</td>
                        </tr>
                    @endif
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-3 py-2 text-gray-400">{{ $row['si'] }}</td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            @if($row['is_addition'])<span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Add: Purchase ·</span>@endif
                            {{ $row['description'] }} <span class="text-xs text-gray-400 font-mono">{{ $row['asset_tag'] }}</span>
                        </td>
                        <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ $row['purchase_date'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ $row['date_from'] ? $row['date_from'].' → '.$row['date_to'] : '—' }}</td>
                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $money($row['cost']) }}</td>
                        <td class="px-3 py-2 text-right text-gray-500">{{ $money($row['residual']) }}</td>
                        <td class="px-3 py-2 text-right text-gray-500">{{ $row['life_years'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-right text-gray-500">{{ $row['rate'] !== null ? $row['rate'].'%' : '—' }}</td>
                        <td class="px-3 py-2 text-right text-gray-500">{{ $row['days'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $money($row['opening_wdv']) }}</td>
                        <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">{{ $money($row['depreciation']) }}</td>
                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $money($row['closing_wdv']) }}</td>
                    </tr>
                @else
                    <tr class="{{ $row['kind'] === 'total' ? 'bg-indigo-50 dark:bg-indigo-900/20 font-bold' : 'bg-gray-50 dark:bg-gray-900/40 font-semibold' }} text-gray-900 dark:text-gray-100">
                        <td></td>
                        <td class="px-3 py-2" colspan="3">{{ $row['description'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $money($row['cost']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $money($row['residual']) }}</td>
                        <td colspan="3"></td>
                        <td class="px-3 py-2 text-right">{{ $money($row['opening_wdv']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $money($row['depreciation']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $money($row['closing_wdv']) }}</td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="12" class="px-4 py-12 text-center text-sm text-gray-400">No depreciation in this financial year.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
