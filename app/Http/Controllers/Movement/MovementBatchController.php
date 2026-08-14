<?php

namespace App\Http\Controllers\Movement;

use App\Http\Controllers\Concerns\StoresApprovalAttachments;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\MovementType;
use App\Models\User;
use App\Rules\ImageUnderSize;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Bulk multi-select movement (P9.1) — one approval covers every selected asset. */
class MovementBatchController extends Controller
{
    use StoresApprovalAttachments;

    public function __construct(private MovementService $movements)
    {
    }

    public function create(Request $request): View
    {
        if (! $request->user()->canAny(['movement.assign', 'movement.transfer'])) {
            abort(403);
        }

        $this->authorize('assets.bulk');

        $data = $request->validate(['asset_ids' => 'required|array|min:1', 'asset_ids.*' => 'exists:assets,id']);
        $assets = Asset::whereIn('id', $data['asset_ids'])->orderBy('name')->get();

        return view('modules.movements.bulk-create', [
            'assets' => $assets,
        ] + $this->formLookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('assets.bulk');

        $data = $request->validate($this->rules());

        $assets = Asset::whereIn('id', $data['asset_ids'])->get();
        $movementType = MovementType::findOrFail($data['movement_type_id']);
        $this->authorize($this->movements->permissionFor($movementType));

        $batch = $this->movements->submitBatch($assets, $data, $request->user());
        $this->storeApprovalAttachments($request, $batch->approval_request_id);

        return redirect()->route('movements.index')
            ->with('success', 'Bulk movement submitted for approval — ' . $assets->count() . ' asset(s).');
    }

    private function rules(): array
    {
        return [
            'asset_ids'        => 'required|array|min:1',
            'asset_ids.*'      => 'exists:assets,id',
            'movement_type_id' => 'required|exists:movement_types,id',
            'to_company_id'    => 'nullable|exists:companies,id',
            'to_location_id'   => 'nullable|exists:locations,id',
            'to_custodian_id'  => 'nullable|exists:employees,id',
            'to_department_id' => 'nullable|exists:departments,id',
            'to_status_id'     => 'nullable|exists:asset_statuses,id',
            'notes'            => 'nullable|string|max:1000',
            // Optional supporting documents for the approver (images compressed client-side).
            'documents'        => 'nullable|array|max:5',
            'documents.*'      => ['file', 'max:20480', new ImageUnderSize(2048)],
        ];
    }

    private function formLookups(): array
    {
        return [
            'movementTypes' => MovementType::where('is_active', true)->orderBy('name')->get(),
            'companies'     => Company::where('is_active', true)->orderBy('name')->get(),
            'locations'     => Location::where('is_active', true)->orderBy('name')->get(),
            'departments'   => Department::where('is_active', true)->orderBy('name')->get(),
            'custodians'    => Employee::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
