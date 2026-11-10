<?php

namespace App\Http\Controllers\Movement;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetMovementBatch;
use App\Models\Employee;
use App\Rules\ImageUnderSize;
use App\Services\MovementService;
use App\Support\CompanyVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phase 2 receipt of physical movements (thin — logic in MovementService):
 *  - incoming:   what is on its way to me (location manager) / everything in transit (stores).
 *  - receive:    scan the barcode → the move lands (or report it incomplete/missing).
 *  - batch:      scan each arriving asset, then "finish" reports the rest short.
 *  - short:      admin worklist of short-received issues + resolution.
 */
class MovementReceiptController extends Controller
{
    public function __construct(private readonly MovementService $movements)
    {
    }

    public function incoming(Request $request): View
    {
        $user = $request->user();
        $managedLocationIds = $this->managedLocationIds($user);

        abort_unless($user->can('movement.verify') || $managedLocationIds !== [], 403);

        $movements = AssetMovement::query()
            ->where('receipt_status', 'in_transit')
            ->when(! $user->can('movement.verify'), fn ($q) => $q->whereIn('to_location_id', $managedLocationIds))
            ->with(['movementType', 'toLocation', 'toCompany', 'requestedBy', 'batch'])
            ->latest('dispatched_at')
            ->paginate(25);

        $this->attachAssets($movements->getCollection());

        return view('modules.movements.incoming', ['movements' => $movements]);
    }

    public function receiveForm(Request $request, AssetMovement $movement): View|RedirectResponse
    {
        $this->authorizeReceipt($request, $movement);

        if (! $movement->isInTransit()) {
            return redirect()->route('movements.index')->with('error', 'This movement is not awaiting receipt.');
        }

        $this->attachAssets(collect([$movement]));

        return view('modules.movements.receive', [
            'movement'    => $movement->load(['movementType', 'toLocation', 'toCustodian', 'toCompany', 'batch']),
            'canOnBehalf' => $request->user()->can('movement.verify'),
        ]);
    }

    public function receive(Request $request, AssetMovement $movement): RedirectResponse
    {
        $this->authorizeReceipt($request, $movement);
        $asset = Asset::withoutGlobalScopes()->find($movement->asset_id);

        $onBehalf = $request->boolean('on_behalf') && $request->user()->can('movement.verify');

        // Possession is proven by scanning (or typing) the asset's barcode; only stores/admins
        // may confirm on someone's behalf without it.
        if (! $onBehalf) {
            $request->validate(['tag_number' => 'required|string']);

            if (! $this->movements->tagMatches($asset, $request->input('tag_number'))) {
                return back()->withErrors(['tag_number' => 'The scanned tag does not match this asset.'])->withInput();
            }
        }

        $data = $this->receiptData($request, $movement);
        $data['method'] = $onBehalf ? 'on_behalf' : 'scan';

        $this->movements->receive($movement, $request->user(), $data);

        $message = $movement->fresh()->isShort()
            ? 'Reported short — an administrator will resolve it.'
            : 'Received — the asset is now at its destination.';

        return $movement->batch_id
            ? redirect()->route('movements.batches.receive', $movement->batch_id)->with('success', $message)
            : redirect()->route('movements.incoming')->with('success', $message);
    }

    public function batchForm(Request $request, AssetMovementBatch $batch): View
    {
        $members = $batch->movements()->with(['movementType', 'toLocation'])->orderBy('id')->get();
        abort_if($members->isEmpty(), 404);
        abort_unless($this->movements->canReceive($request->user(), $members->first()), 403);

        $this->attachAssets($members);

        return view('modules.movements.batch-receive', [
            'batch'       => $batch,
            'members'     => $members,
            'canOnBehalf' => $request->user()->can('movement.verify'),
        ]);
    }

    /** One scan inside a batch: find the arriving member by its tag and receive it as OK. */
    public function batchScan(Request $request, AssetMovementBatch $batch): RedirectResponse
    {
        $data = $request->validate(['tag_number' => 'required|string']);
        $members = $batch->movements()->where('receipt_status', 'in_transit')->get();
        abort_unless($members->isEmpty() || $this->movements->canReceive($request->user(), $members->first()), 403);

        $match = $members->first(fn (AssetMovement $m) => $this->movements->tagMatches(
            Asset::withoutGlobalScopes()->find($m->asset_id), $data['tag_number'],
        ));

        if (! $match) {
            return back()->withErrors(['tag_number' => 'That tag is not an asset still in transit in this batch.']);
        }

        $this->movements->receive($match, $request->user(), ['condition' => 'ok', 'method' => 'scan']);

        return back()->with('success', 'Received: '.Asset::labelFor(Asset::withoutGlobalScopes()->find($match->asset_id)));
    }

    /** "Finish receipt": every member not scanned is reported missing (short). */
    public function batchFinish(Request $request, AssetMovementBatch $batch): RedirectResponse
    {
        $first = $batch->movements()->first();
        abort_unless($first && $this->movements->canReceive($request->user(), $first), 403);

        $count = $this->movements->finishBatchReceipt($batch, $request->user(), $request->input('notes'));

        return redirect()->route('movements.incoming')->with('success', $count
            ? "{$count} asset(s) not received were reported short — an administrator will resolve them."
            : 'Batch receipt complete.');
    }

    public function shortIndex(): View
    {
        $this->authorize('movement.verify');

        $movements = AssetMovement::query()
            ->where('receipt_status', 'short')
            ->with(['movementType', 'toLocation', 'toCustodian', 'toCompany', 'shortReportedBy', 'batch'])
            ->latest('short_reported_at')
            ->paginate(25);

        $this->attachAssets($movements->getCollection());

        return view('modules.movements.short-index', ['movements' => $movements]);
    }

    public function resolveShort(Request $request, AssetMovement $movement): RedirectResponse
    {
        $this->authorize('movement.verify');

        $data = $request->validate([
            'resolution'       => 'required|in:'.implode(',', array_keys(AssetMovement::RESOLUTIONS)),
            'resolution_notes' => 'required|string|max:2000',
        ]);

        $this->movements->resolveShort($movement, $data['resolution'], $request->user(), $data['resolution_notes']);

        return back()->with('success', $data['resolution'] === 'lost'
            ? 'Loss write-off submitted for approval.'
            : 'Short-received issue resolved.');
    }

    // ---------------------------------------------------------------- helpers

    private function authorizeReceipt(Request $request, AssetMovement $movement): void
    {
        abort_unless($this->movements->canReceive($request->user(), $movement->loadMissing('toLocation.manager')), 403);
    }

    /** @return int[] */
    private function managedLocationIds($user): array
    {
        $employeeIds = Employee::where('user_id', $user->id)->pluck('id');

        return $employeeIds->isEmpty()
            ? []
            : \App\Models\Location::whereIn('manager_employee_id', $employeeIds)->pluck('id')->all();
    }

    /**
     * The receiver of an inter-company transfer sees the movement (company isolation lets both
     * sides) but not yet the asset, which still belongs to the sender until received.
     */
    private function attachAssets($movements): void
    {
        $assets = CompanyVisibility::unrestricted(fn () => Asset::withoutGlobalScopes()
            ->with('status')
            ->whereIn('id', collect($movements)->pluck('asset_id'))->get()->keyBy('id'));

        foreach ($movements as $movement) {
            $movement->setRelation('asset', $assets->get($movement->asset_id));
        }
    }

    private function receiptData(Request $request, AssetMovement $movement): array
    {
        $data = $request->validate([
            'verification_condition' => ['required', 'in:ok,damaged,incomplete'],
            'verification_notes'     => ['nullable', 'required_if:verification_condition,incomplete', 'string', 'max:2000'],
            'photos'                 => ['nullable', 'array', 'max:8'],
            'photos.*'               => ['file', 'max:20480', new ImageUnderSize(2048)],
        ], ['verification_notes.required_if' => 'Describe what is missing or incomplete.']);

        $photos = [];
        foreach ($request->file('photos', []) as $photo) {
            $photos[] = $photo->store("movements/{$movement->id}/verification", 'public');
        }

        return [
            'condition' => $data['verification_condition'],
            'notes'     => $data['verification_notes'] ?? null,
            'photos'    => $photos,
        ];
    }
}
