<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance)
    {
    }

    /** Global list across every asset — GET /maintenance. */
    public function index(Request $request): View
    {
        $this->authorize('maintenance.manage');

        $query = MaintenanceRecord::query()->with(['asset.company', 'maintenanceType', 'loggedBy']);

        if ($request->filled('maintenance_type_id')) {
            $query->where('maintenance_type_id', $request->input('maintenance_type_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('asset', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('asset_tag', 'like', "%{$search}%"));
        }

        return view('modules.maintenance.index', [
            'records'          => $query->latest('id')->paginate(20)->withQueryString(),
            'maintenanceTypes' => MaintenanceType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Asset $asset): View
    {
        $this->authorize('maintenance.manage');

        return view('modules.maintenance.form', [
            'asset'            => $asset,
            'record'           => new MaintenanceRecord(),
            'maintenanceTypes' => MaintenanceType::where('is_active', true)->orderBy('name')->get(),
        ] + $this->capitalizationContext($asset));
    }

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $data = $this->capitalizationInput($request, $request->validate($this->rules()), null);

        $this->maintenance->log($asset, $data, $request->user());

        return redirect()->route('assets.show', $asset)->with('success', 'Maintenance record logged.');
    }

    public function edit(Asset $asset, MaintenanceRecord $maintenance): View
    {
        $this->authorize('maintenance.manage');

        return view('modules.maintenance.form', [
            'asset'            => $asset,
            'record'           => $maintenance,
            'maintenanceTypes' => MaintenanceType::where('is_active', true)->orderBy('name')->get(),
        ] + $this->capitalizationContext($asset));
    }

    public function update(Request $request, Asset $asset, MaintenanceRecord $maintenance): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $data = $this->capitalizationInput($request, $request->validate($this->rules()), $maintenance);

        $this->maintenance->update($maintenance, $data, $request->user());

        return redirect()->route('assets.show', $asset)->with('success', 'Maintenance record updated.');
    }

    public function destroy(Asset $asset, MaintenanceRecord $maintenance): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $this->maintenance->delete($maintenance);

        return redirect()->route('assets.show', $asset)->with('success', 'Maintenance record deleted.');
    }

    private function rules(): array
    {
        return [
            'maintenance_type_id' => 'required|exists:maintenance_types,id',
            'status'              => 'required|in:scheduled,in_progress,completed,cancelled',
            'scheduled_date'      => 'nullable|date',
            // Preventive work that repeats: completing it schedules the next one this many months on.
            'repeat_every_months' => 'nullable|integer|min:1|max:120',
            // Work can be scheduled for the future, but it can't have been performed in the future.
            'performed_date'      => 'nullable|date|before_or_equal:today',
            'vendor'              => 'nullable|string|max:255',
            'cost'                => 'nullable|required_if:is_capitalized,1|numeric|min:0',
            'description'         => 'nullable|string',
            // Capitalisation: the amount added to the asset's book value (at most the cost
            // when a cost is entered) and any extra useful life the work gives.
            'is_capitalized'                => 'sometimes|boolean',
            'capitalized_amount'            => 'nullable|required_if:is_capitalized,1|numeric|min:0.01|lte:cost',
            'additional_useful_life_months' => 'nullable|integer|min:0|max:600',
        ];
    }

    /**
     * Normalise the capitalisation fields: an unticked box clears them, and once a
     * capitalisation is pending or applied the posted values are ignored (it is in the
     * depreciation approval, or already in the schedule).
     */
    private function capitalizationInput(Request $request, array $data, ?MaintenanceRecord $record): array
    {
        if ($record?->capitalizationLocked()) {
            unset($data['is_capitalized'], $data['capitalized_amount'], $data['additional_useful_life_months']);

            return $data;
        }

        $data['is_capitalized'] = $request->boolean('is_capitalized');
        if (! $data['is_capitalized']) {
            $data['capitalized_amount'] = null;
            $data['additional_useful_life_months'] = null;
        }

        return $data;
    }

    /** Whether this asset can take a capitalisation at all (it must be depreciating). */
    private function capitalizationContext(Asset $asset): array
    {
        return [
            'canCapitalize' => $asset->activeDepreciationSetting() !== null && ! $asset->isExternallyOwned(),
        ];
    }
}
