<?php

namespace App\Http\Controllers\Movement;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\MovementType;
use App\Models\User;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function __construct(private MovementService $movements)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('assets.view');

        // Every row here is one asset actually moving, including the members of a bulk
        // batch. An earlier `whereNull('batch_id')` filter hid batch children on the
        // assumption that the batch itself would get its own row — it never did, so a
        // submitted bulk movement was invisible here. Children carry a "Bulk" badge
        // instead, which keeps one row per asset movement without losing the grouping.
        $query = AssetMovement::query()
            ->with(['asset', 'movementType', 'toCompany', 'toLocation', 'toCustodian', 'requestedBy', 'batch']);

        if ($request->filled('movement_type_id')) {
            $query->where('movement_type_id', $request->input('movement_type_id'));
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

        $movements = $query->latest('id')->paginate(20)->withQueryString();

        return view('modules.movements.index', [
            'movements'     => $movements,
            'movementTypes' => MovementType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        if (! $request->user()->canAny(['movement.assign', 'movement.transfer'])) {
            abort(403);
        }

        $preselected = $request->filled('asset_id') ? Asset::find($request->integer('asset_id')) : null;

        return view('modules.movements.create', [
            'asset' => $preselected,
        ] + $this->formLookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $asset = Asset::findOrFail($data['asset_id']);
        $movementType = MovementType::findOrFail($data['movement_type_id']);
        $this->authorize($this->movements->permissionFor($movementType));

        $this->movements->submit($asset, $data, $request->user());

        return redirect()->route('assets.show', $asset)->with('success', 'Movement submitted for approval.');
    }

    public function verify(Request $request, AssetMovement $movement): RedirectResponse
    {
        $this->authorize('movement.verify');

        $this->movements->verify($movement, $request->user());

        return back()->with('success', 'Movement verified.');
    }

    private function rules(): array
    {
        return [
            'asset_id'         => 'required|exists:assets,id',
            'movement_type_id' => 'required|exists:movement_types,id',
            'to_company_id'    => 'nullable|exists:companies,id',
            'to_location_id'   => 'nullable|exists:locations,id',
            'to_custodian_id'  => 'nullable|exists:users,id',
            'to_department_id' => 'nullable|exists:departments,id',
            'to_status_id'     => 'nullable|exists:asset_statuses,id',
            'notes'            => 'nullable|string|max:1000',
        ];
    }

    private function formLookups(): array
    {
        return [
            'assets'        => Asset::where('is_active', true)->orderBy('name')->get(),
            'movementTypes' => MovementType::where('is_active', true)->orderBy('name')->get(),
            'companies'     => Company::where('is_active', true)->orderBy('name')->get(),
            'locations'     => Location::where('is_active', true)->orderBy('name')->get(),
            'departments'   => Department::where('is_active', true)->orderBy('name')->get(),
            'custodians'    => User::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
