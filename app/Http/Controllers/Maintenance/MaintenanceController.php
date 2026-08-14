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
        ]);
    }

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $data = $request->validate($this->rules());

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
        ]);
    }

    public function update(Request $request, Asset $asset, MaintenanceRecord $maintenance): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $data = $request->validate($this->rules());

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
            // Work can be scheduled for the future, but it can't have been performed in the future.
            'performed_date'      => 'nullable|date|before_or_equal:today',
            'vendor'              => 'nullable|string|max:255',
            'cost'                => 'nullable|numeric|min:0',
            'description'         => 'nullable|string',
        ];
    }
}
