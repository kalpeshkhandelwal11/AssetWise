<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\DepreciationMethod;
use App\Services\DepreciationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetDepreciationController extends Controller
{
    public function __construct(private readonly DepreciationService $depreciation)
    {
    }

    /** Per-asset override form: current active settings + a "request change" form prefilled from the category default. */
    public function edit(Asset $asset): View
    {
        $this->authorize('depreciation.manage');

        $asset->load('category.depreciationDefault');

        return view('modules.depreciation.asset-edit', [
            'asset'    => $asset,
            'setting'  => $asset->activeDepreciationSetting(),
            'pending'  => $asset->depreciationRequests()->where('status', 'pending_approval')->first(),
            'defaults' => $this->depreciation->resolveDefaults($asset),
            'methods'  => DepreciationMethod::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('depreciation.manage');

        $data = $request->validate([
            'depreciation_method_id' => ['required', Rule::exists('depreciation_methods', 'id')->where('is_active', true)],
            'useful_life_months'     => ['required', 'integer', 'min:1'],
            'salvage_value'          => ['nullable', 'numeric', 'min:0'],
            'salvage_percent'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'start_date'             => ['required', 'date'],
            'cost_basis'             => ['required', 'numeric', 'min:0'],
        ]);

        $changeType = $asset->activeDepreciationSetting() ? 'revision' : 'initial';
        $this->depreciation->submitSettingChange($asset, $data, $request->user(), $changeType);

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Depreciation change submitted for approval.');
    }

    public function schedule(Request $request, Asset $asset): View
    {
        $this->authorize('depreciation.view');

        $setting = $asset->activeDepreciationSetting();
        $lines = $setting ? $setting->scheduleLines()->orderBy('period_year')->orderBy('period_month')->get() : collect();

        // Calculation stays monthly (daily-prorated); "yearly" is a presentation rollup only.
        $view = $request->input('view') === 'yearly' ? 'yearly' : 'monthly';
        $rows = $view === 'yearly' ? $this->rollUpByYear($lines) : $this->monthlyRows($lines);

        return view('modules.depreciation.schedule', [
            'asset'   => $asset,
            'setting' => $setting,
            'rows'    => $rows,
            'view'    => $view,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function monthlyRows($lines): \Illuminate\Support\Collection
    {
        return $lines->map(fn ($l) => [
            'label'        => \Carbon\Carbon::create($l->period_year, $l->period_month, 1)->format('M Y'),
            'days'         => $l->days_in_period,
            'depreciation' => $l->depreciation_amount,
            'accumulated'  => $l->accumulated_depreciation,
            'book_value'   => $l->closing_book_value,
            'status'       => $l->status,
        ])->values();
    }

    /**
     * Roll the monthly lines up per calendar year: depreciation summed, year-end accumulated
     * and book value taken from the last month, status posted/partial/scheduled by how many
     * of the year's months have posted.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function rollUpByYear($lines): \Illuminate\Support\Collection
    {
        return $lines->groupBy('period_year')->map(function ($yearLines, $year) {
            $last = $yearLines->sortBy('period_month')->last();
            $postedCount = $yearLines->where('status', 'posted')->count();
            $status = match (true) {
                $postedCount === 0                    => 'scheduled',
                $postedCount === $yearLines->count()  => 'posted',
                default                                => 'partial',
            };

            return [
                'label'        => (string) $year,
                'days'         => $yearLines->sum('days_in_period'),
                'depreciation' => $yearLines->sum('depreciation_amount'),
                'accumulated'  => $last->accumulated_depreciation,
                'book_value'   => $last->closing_book_value,
                'status'       => $status,
            ];
        })->values();
    }
}
