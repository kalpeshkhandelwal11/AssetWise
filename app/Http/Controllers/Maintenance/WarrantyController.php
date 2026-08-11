<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\WarrantyRecord;
use App\Services\WarrantyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarrantyController extends Controller
{
    public function __construct(private readonly WarrantyService $warranty)
    {
    }

    /** Global list across every asset — GET /warranty. */
    public function index(Request $request): View
    {
        $this->authorize('maintenance.manage');

        $query = WarrantyRecord::query()->with(['asset.company', 'createdBy']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('asset', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('asset_tag', 'like', "%{$search}%"));
        }

        return view('modules.warranty.index', [
            'records' => $query->latest('end_date')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(Asset $asset): View
    {
        $this->authorize('maintenance.manage');

        return view('modules.warranty.form', ['asset' => $asset, 'record' => new WarrantyRecord()]);
    }

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $data = $request->validate($this->rules());

        $this->warranty->create($asset, $data, $request->user());

        return redirect()->route('assets.show', $asset)->with('success', 'Warranty record added.');
    }

    public function edit(Asset $asset, WarrantyRecord $warranty): View
    {
        $this->authorize('maintenance.manage');

        return view('modules.warranty.form', ['asset' => $asset, 'record' => $warranty]);
    }

    public function update(Request $request, Asset $asset, WarrantyRecord $warranty): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $data = $request->validate($this->rules());

        $this->warranty->update($warranty, $data);

        return redirect()->route('assets.show', $asset)->with('success', 'Warranty record updated.');
    }

    public function destroy(Asset $asset, WarrantyRecord $warranty): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $this->warranty->delete($warranty);

        return redirect()->route('assets.show', $asset)->with('success', 'Warranty record removed.');
    }

    private function rules(): array
    {
        return [
            'provider'   => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date'   => 'required|date',
            'terms'      => 'nullable|string',
        ];
    }
}
