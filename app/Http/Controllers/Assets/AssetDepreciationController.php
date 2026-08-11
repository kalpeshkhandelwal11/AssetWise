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

    public function schedule(Asset $asset): View
    {
        $this->authorize('depreciation.view');

        $setting = $asset->activeDepreciationSetting();

        return view('modules.depreciation.schedule', [
            'asset'   => $asset,
            'setting' => $setting,
            'lines'   => $setting
                ? $setting->scheduleLines()->get()
                : collect(),
        ]);
    }
}
