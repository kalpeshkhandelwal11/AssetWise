<?php

namespace App\Http\Controllers\Movement;

use App\Http\Controllers\Concerns\StoresApprovalAttachments;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\MovementType;
use App\Models\User;
use App\Rules\ImageUnderSize;
use App\Services\MovementService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MovementReportExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

class MovementController extends Controller
{
    use StoresApprovalAttachments;

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
            // "Cancelled" is status=rejected + cancelled_at set; keep the two distinct in filters.
            match ($request->input('status')) {
                'cancelled' => $query->whereNotNull('cancelled_at'),
                'rejected'  => $query->where('status', 'rejected')->whereNull('cancelled_at'),
                default     => $query->where('status', $request->input('status')),
            };
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

        $movement = $this->movements->submit($asset, $data, $request->user());
        $this->storeApprovalAttachments($request, $movement->approval_request_id);

        return redirect()->route('assets.show', $asset)->with('success', 'Movement submitted for approval.');
    }

    /** Confirmation page for verifying a movement — non-admins scan/type the asset's tag here. */
    public function verifyForm(Request $request, AssetMovement $movement): View
    {
        $this->authorize('movement.verify');

        return view('modules.movements.verify', [
            'movement' => $movement->load(['asset', 'movementType', 'toLocation', 'toCustodian']),
            'isAdmin'  => $request->user()->hasRole('Super Admin'),
        ]);
    }

    public function verify(Request $request, AssetMovement $movement): RedirectResponse
    {
        $this->authorize('movement.verify');

        // Non-admins must confirm physical possession by scanning/typing a tag that matches the
        // moved asset (its physical pool tag or its Asset ID). Super Admin bypasses.
        if (! $request->user()->hasRole('Super Admin')) {
            $data = $request->validate(['tag_number' => 'required|string']);
            $scanned = $this->normaliseTag($data['tag_number']);
            $asset = $movement->asset;
            $valid = collect([$asset?->activeTag()?->tag_number, $asset?->asset_tag])
                ->filter()
                ->contains(fn ($t) => strcasecmp($t, $scanned) === 0);

            if (! $valid) {
                return back()->withErrors(['tag_number' => 'The scanned tag does not match this asset.']);
            }
        }

        $this->movements->verify($movement, $request->user());

        return redirect()->route('movements.index')->with('success', 'Movement verified.');
    }

    /** Reduce a scanned QR payload (url("/scan/{n}")) or a bare barcode to the tag number. */
    private function normaliseTag(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('~/scan/([^/?\#]+)~', $raw, $m)) {
            return urldecode($m[1]);
        }
        return $raw;
    }

    /** Cancel (withdraw) a pending movement — requester's own, or any pending for an admin. */
    public function cancel(Request $request, AssetMovement $movement): RedirectResponse
    {
        $user = $request->user();
        abort_unless($movement->requested_by === $user->id || $user->hasRole('Super Admin'), 403);

        $this->movements->cancel($movement, $user);

        return back()->with('success', 'Movement cancelled.');
    }

    /** Download the currently-filtered movement history as Excel (reuses the M14 export). */
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('assets.view');

        $filters = array_filter($request->only(['movement_type_id', 'status', 'search']), fn ($v) => $v !== null && $v !== '');

        // The shared ReportService only knows the real status column; a "cancelled" filter maps
        // to the rejected set (cancelled rows keep status=rejected).
        if (($filters['status'] ?? null) === 'cancelled') {
            $filters['status'] = 'rejected';
        }

        return Excel::download(
            new MovementReportExport($filters, app(ReportService::class)),
            'movements-' . now()->format('Ymd-His') . '.xlsx',
        );
    }

    private function rules(): array
    {
        return [
            'asset_id'         => 'required|exists:assets,id',
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
            'assets'        => Asset::where('is_active', true)->orderBy('name')->get(),
            'movementTypes' => MovementType::where('is_active', true)->orderBy('name')->get(),
            'companies'     => Company::where('is_active', true)->orderBy('name')->get(),
            'locations'     => Location::where('is_active', true)->orderBy('name')->get(),
            'departments'   => Department::where('is_active', true)->orderBy('name')->get(),
            'custodians'    => Employee::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
