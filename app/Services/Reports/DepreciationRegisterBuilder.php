<?php

namespace App\Services\Reports;

use App\Models\Asset;
use App\Models\AssetDepreciationSetting;
use App\Models\Company;
use App\Models\DepreciationScheduleLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * "Depreciation Register (FY)" — one row per asset for an Indian financial year (Apr–Mar),
 * in the layout of the company's Schedule II register: cost, residual, life, SLM rate, days
 * used, depreciation for the year and closing WDV, grouped by Schedule II class with
 * subtotals. Additions dated inside the FY are flagged "Add: Purchase".
 *
 * Figures come from the stored schedule lines (the same calendar-day SLM the system posts),
 * so the register always reconciles with what was actually charged. Non-depreciable classes
 * (Land) appear at cost with zero depreciation, as in the source sheet.
 */
class DepreciationRegisterBuilder
{
    public static function currentFyStartYear(?Carbon $on = null): int
    {
        $on ??= now();

        return $on->month >= 4 ? $on->year : $on->year - 1;
    }

    public static function fyLabel(int $startYear): string
    {
        return sprintf('FY %d-%02d', $startYear, ($startYear + 1) % 100);
    }

    /** @return Collection<int, array<string, mixed>> asset rows, class subtotal rows, and a grand total */
    public function rows(array $filters): Collection
    {
        $fy = (int) ($filters['fy'] ?? self::currentFyStartYear());
        $fyStart = Carbon::create($fy, 4, 1)->startOfDay();
        $fyEnd = Carbon::create($fy + 1, 3, 31)->startOfDay();
        $fromKey = $fy * 100 + 4;
        $toKey = ($fy + 1) * 100 + 3;

        $assetFilter = function ($q) use ($filters) {
            if (! empty($filters['company_id'])) {
                $q->where('company_id', $filters['company_id']);
            }
            if (! empty($filters['category_id'])) {
                $q->where('category_id', $filters['category_id']);
            }
        };

        // Schedule lines falling inside the FY, grouped per asset (all of its settings — an
        // inter-company reset or a revision mid-year still adds up to the year's charge).
        $lines = DepreciationScheduleLine::query()
            ->whereHas('asset', $assetFilter)
            ->whereRaw('(period_year * 100 + period_month) BETWEEN ? AND ?', [$fromKey, $toKey])
            ->orderBy('period_year')->orderBy('period_month')
            ->get()
            ->groupBy('asset_id');

        $settings = AssetDepreciationSetting::query()
            ->whereIn('asset_id', $lines->keys())
            ->with(['asset.category.parent', 'asset.company'])
            ->orderByDesc('is_active')->orderByDesc('id')
            ->get()
            ->unique('asset_id')
            ->keyBy('asset_id');

        $rows = collect();

        foreach ($lines as $assetId => $assetLines) {
            $setting = $settings->get($assetId);
            $asset = $setting?->asset;

            if (! $setting || ! $asset) {
                continue;
            }

            $first = $assetLines->first();
            $last = $assetLines->last();
            $class = $asset->category?->effectiveDepreciationClass();
            $lifeYears = round($setting->useful_life_months / 12, 2);
            $start = Carbon::parse($setting->start_date)->startOfDay();
            $dateFrom = $start->greaterThan($fyStart) ? $start : $fyStart;
            $stop = $setting->stopped_at ? Carbon::parse($setting->stopped_at) : null;
            $endOfLife = $start->copy()->addMonths($setting->useful_life_months)->subDay();
            $dateTo = collect([$fyEnd, $stop, $endOfLife])->filter()->sort()->first();

            $rows->push([
                'kind'          => 'asset',
                'class'         => $class?->name ?? 'Unclassified',
                'asset_tag'     => $asset->asset_tag,
                'description'   => $asset->name,
                'is_addition'   => $start->betweenIncluded($fyStart, $fyEnd),
                'purchase_date' => $asset->purchase_date?->format('d-m-Y'),
                'date_from'     => $dateFrom->format('d-m-Y'),
                'date_to'       => $dateTo->format('d-m-Y'),
                'cost'          => (float) $setting->cost_basis,
                'residual'      => (float) $setting->salvage_value,
                'life_years'    => $lifeYears,
                'method'        => 'SLM',
                'rate'          => $lifeYears > 0 ? round(100 / $lifeYears, 2) : null,
                'days'          => (int) $assetLines->sum('days_in_period'),
                'opening_wdv'   => (float) $first->opening_book_value,
                'depreciation'  => round((float) $assetLines->sum('depreciation_amount'), 2),
                'closing_wdv'   => (float) $last->closing_book_value,
            ]);
        }

        // Non-depreciable classes (Land): at cost, no depreciation.
        Asset::query()
            ->where($assetFilter)
            ->whereNotIn('id', $lines->keys())
            ->whereNotNull('purchase_cost')
            ->where(fn ($q) => $q->whereNull('purchase_date')->orWhereDate('purchase_date', '<=', $fyEnd))
            ->with('category.parent')
            ->get()
            ->filter(fn (Asset $a) => $a->category?->effectiveDepreciationClass()?->is_depreciable === false)
            ->each(function (Asset $asset) use ($rows, $fyStart, $fyEnd) {
                $rows->push([
                    'kind'          => 'asset',
                    'class'         => $asset->category->effectiveDepreciationClass()->name,
                    'asset_tag'     => $asset->asset_tag,
                    'description'   => $asset->name,
                    'is_addition'   => $asset->purchase_date?->betweenIncluded($fyStart, $fyEnd) ?? false,
                    'purchase_date' => $asset->purchase_date?->format('d-m-Y'),
                    'date_from'     => null,
                    'date_to'       => null,
                    'cost'          => (float) $asset->purchase_cost,
                    'residual'      => null,
                    'life_years'    => null,
                    'method'        => '—',
                    'rate'          => null,
                    'days'          => null,
                    'opening_wdv'   => (float) $asset->purchase_cost,
                    'depreciation'  => 0.0,
                    'closing_wdv'   => (float) $asset->purchase_cost,
                ]);
            });

        return $this->withSubtotals($rows->sortBy([['class', 'asc'], ['is_addition', 'asc'], ['description', 'asc']])->values());
    }

    /** Heading for the PDF/Excel: FY + company identity, like the source register's header. */
    public function title(array $filters): string
    {
        $fy = (int) ($filters['fy'] ?? self::currentFyStartYear());
        $title = 'Depreciation Register (Schedule II, SLM) — '.self::fyLabel($fy);

        if (! empty($filters['company_id']) && $company = Company::find($filters['company_id'])) {
            $title .= ' — '.($company->legal_name ?: $company->name).($company->cin ? ' · CIN '.$company->cin : '');
        }

        return $title;
    }

    private function withSubtotals(Collection $rows): Collection
    {
        $out = collect();
        $si = 0;

        foreach ($rows->groupBy('class') as $class => $group) {
            foreach ($group as $row) {
                $out->push($row + ['si' => ++$si]);
            }

            $out->push($this->total('subtotal', 'Total — '.$class, $group));
        }

        if ($rows->isNotEmpty()) {
            $out->push($this->total('total', 'GRAND TOTAL', $rows));
        }

        return $out;
    }

    private function total(string $kind, string $label, Collection $rows): array
    {
        return [
            'kind'         => $kind,
            'description'  => $label,
            'cost'         => round($rows->sum('cost'), 2),
            'residual'     => round($rows->sum('residual'), 2),
            'opening_wdv'  => round($rows->sum('opening_wdv'), 2),
            'depreciation' => round($rows->sum('depreciation'), 2),
            'closing_wdv'  => round($rows->sum('closing_wdv'), 2),
        ];
    }
}
