<?php

namespace App\Http\Controllers\Kits;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Kit;
use App\Models\KitAssignment;
use App\Models\Location;
use App\Models\MovementType;
use App\Models\Setting;
use App\Models\User;
use App\Services\KitAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class KitAssignmentController extends Controller
{
    public function __construct(private readonly KitAssignmentService $assignments)
    {
    }

    public function index(): View
    {
        $this->authorize('kits.view');

        $assignments = KitAssignment::with(['kit', 'movementType', 'toCustodian', 'toCompany', 'requestedBy'])
            ->withCount('movements')
            ->latest('id')
            ->paginate(20);

        return view('modules.kits.assignments.index', ['assignments' => $assignments]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kits.assign');

        return view('modules.kits.assignments.create', [
            'preselectedKit' => $request->filled('kit_id') ? Kit::with('items.kitAssets')->find($request->integer('kit_id')) : null,
            'kits'           => Kit::where('is_active', true)->with('items.kitAssets')->orderBy('name')->get(),
            'mode'           => Setting::get('kit_assignment_approval_mode', 'single'),
        ] + $this->lookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kits.assign');

        $data = $request->validate([
            'kit_id'           => 'nullable|exists:kits,id',
            'asset_ids'        => 'nullable|array',
            'asset_ids.*'      => 'exists:assets,id',
            'movement_type_id' => 'required|exists:movement_types,id',
            'to_company_id'    => 'nullable|exists:companies,id',
            'to_custodian_id'  => 'nullable|exists:employees,id',
            'to_location_id'   => 'nullable|exists:locations,id',
            'to_department_id' => 'nullable|exists:departments,id',
        ]);

        $assets = $this->resolveAssets($data);

        if ($assets->isEmpty()) {
            return back()->withInput()->withErrors(['asset_ids' => 'Select at least one asset (or a kit with linked assets).']);
        }

        $assignment = $this->assignments->submit($data, $assets, $request->user());

        return redirect()->route('kit-assignments.show', $assignment)->with('success', 'Kit assignment submitted for approval.');
    }

    public function show(KitAssignment $kitAssignment): View
    {
        $this->authorize('kits.view');

        $kitAssignment->load([
            'kit', 'movementType', 'toCompany', 'toCustodian', 'toLocation', 'toDepartment',
            'requestedBy', 'approvalRequest', 'movements.asset', 'movements.movementType',
        ]);

        return view('modules.kits.assignments.show', ['assignment' => $kitAssignment]);
    }

    public function returnKit(KitAssignment $kitAssignment, Request $request): RedirectResponse
    {
        $this->authorize('kits.assign');

        $return = $this->assignments->returnKit($kitAssignment, $request->user());

        return redirect()->route('kit-assignments.show', $return)->with('success', 'Kit return submitted for approval.');
    }

    /** Assets come from the explicit multi-select, else from the chosen kit's linked slot assets. */
    private function resolveAssets(array $data): Collection
    {
        if (! empty($data['asset_ids'])) {
            return Asset::whereIn('id', $data['asset_ids'])->get();
        }

        if (! empty($data['kit_id'])) {
            $kit = Kit::with('items.kitAssets')->find($data['kit_id']);

            return $kit
                ? Asset::whereIn('id', $kit->items->flatMap->kitAssets->pluck('asset_id')->unique())->get()
                : collect();
        }

        return collect();
    }

    private function lookups(): array
    {
        return [
            'assets'        => Asset::where('is_active', true)->orderBy('name')->get(),
            // Kits are moved with any type except RETURN, which is reserved for the kit-return action.
            'movementTypes' => MovementType::where('is_active', true)->where('code', '!=', 'RETURN')->orderBy('name')->get(),
            'companies'     => Company::where('is_active', true)->orderBy('name')->get(),
            'locations'     => Location::where('is_active', true)->orderBy('name')->get(),
            'departments'   => Department::where('is_active', true)->orderBy('name')->get(),
            'custodians'    => Employee::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
