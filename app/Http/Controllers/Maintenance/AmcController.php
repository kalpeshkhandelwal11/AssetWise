<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\AmcContract;
use App\Models\Asset;
use App\Services\AmcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AmcController extends Controller
{
    public function __construct(private readonly AmcService $amc)
    {
    }

    /** Global list across every asset — GET /amc. */
    public function index(Request $request): View
    {
        $this->authorize('maintenance.manage');

        $query = AmcContract::query()->with(['asset.company', 'createdBy']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('asset', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('asset_tag', 'like', "%{$search}%"));
        }

        return view('modules.amc.index', [
            'contracts' => $query->latest('end_date')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(Asset $asset): View
    {
        $this->authorize('maintenance.manage');

        return view('modules.amc.form', ['asset' => $asset, 'contract' => new AmcContract()]);
    }

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $data = $request->validate($this->rules());

        $this->amc->create($asset, $data, $request->user());

        return redirect()->route('assets.show', $asset)->with('success', 'AMC contract added.');
    }

    public function edit(Asset $asset, AmcContract $amc): View
    {
        $this->authorize('maintenance.manage');

        return view('modules.amc.form', ['asset' => $asset, 'contract' => $amc]);
    }

    public function update(Request $request, Asset $asset, AmcContract $amc): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $data = $request->validate($this->rules());

        $this->amc->update($amc, $data);

        return redirect()->route('assets.show', $asset)->with('success', 'AMC contract updated.');
    }

    public function destroy(Asset $asset, AmcContract $amc): RedirectResponse
    {
        $this->authorize('maintenance.manage');

        $this->amc->delete($amc);

        return redirect()->route('assets.show', $asset)->with('success', 'AMC contract removed.');
    }

    private function rules(): array
    {
        return [
            'vendor'     => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'coverage'   => 'nullable|string',
            'cost'       => 'nullable|numeric|min:0',
        ];
    }
}
