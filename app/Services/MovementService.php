<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetMovementBatch;
use App\Models\AssetStatus;
use App\Models\AssetStatusHistory;
use App\Models\MovementType;
use App\Models\User;
use App\Support\CompanyVisibility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assignment / Return / Transfer / Custodian Change / Inter-Company Transfer (M09).
 * Every movement is approval-gated — submit() only opens the request; apply()/applyBulk()
 * run once WorkflowService fires ApprovalRequestApproved.
 *
 * Phase 2 — physical movements go IN TRANSIT. For Transfer (with a destination location),
 * Return and Inter-Company Transfer, approval only dispatches: the asset becomes IN_TRANSIT
 * and keeps its old location/custodian/company. The move lands when the destination —
 * the location manager or a movement.verify holder (stores/admin) — scans the barcode
 * (receive()). Assignment / Custodian Change apply immediately at approval (the custodian
 * may have no login, so there is no receipt step). Anything not received can be reported
 * short and is then resolved by an admin (resolveShort()).
 */
class MovementService
{
    /** Movement type codes gated by movement.transfer; everything else needs only movement.assign. */
    private const TRANSFER_TYPE_CODES = ['TRANSFER', 'INTER_COMPANY_TRANSFER'];

    /** Physical movements: approval dispatches, receipt (barcode scan) applies. */
    public const TRANSIT_TYPE_CODES = ['TRANSFER', 'RETURN', 'INTER_COMPANY_TRANSFER'];

    /** Reported through the asset_loss approval (write-off), not the transfer workflow. */
    public const LOSS_TYPE_CODES = ['LOST', 'STOLEN'];

    /** Memoised status-code -> id lookups for defaultStatusIdForType() across a bulk apply. */
    private array $statusIdByCode = [];

    public function __construct(
        private WorkflowService $workflows,
        private NotificationService $notifications,
        private DepreciationService $depreciation,
    ) {
    }

    public function permissionFor(MovementType $movementType): string
    {
        return in_array($movementType->code, self::TRANSFER_TYPE_CODES, true)
            ? 'movement.transfer'
            : 'movement.assign';
    }

    /**
     * Single-asset submission — the movement itself is the approvable. Wrapped in a
     * transaction so a WorkflowService::submit() failure (e.g. the module's active
     * workflow gets deactivated between page load and submit) never leaves an orphaned
     * pending_approval row that would then falsely trip hasPendingMovement().
     */
    public function submit(Asset $asset, array $data, User $actor): AssetMovement
    {
        $movementType = MovementType::findOrFail($data['movement_type_id']);
        $this->assertMovable($asset, $movementType, $data);

        return DB::transaction(function () use ($asset, $data, $movementType, $actor) {
            $movement = AssetMovement::create([
                'asset_id'           => $asset->id,
                'movement_type_id'   => $movementType->id,
                'from_company_id'    => $asset->company_id,
                'to_company_id'      => $data['to_company_id'] ?? null,
                'from_location_id'   => $asset->location_id,
                'to_location_id'     => $data['to_location_id'] ?? null,
                'from_custodian_id'  => $asset->custodian_id,
                'to_custodian_id'    => $data['to_custodian_id'] ?? null,
                'from_department_id' => $asset->department_id,
                'to_department_id'   => $data['to_department_id'] ?? null,
                'to_status_id'       => $data['to_status_id'] ?? null,
                'status'             => 'pending_approval',
                'notes'              => $data['notes'] ?? null,
                'requested_by'       => $actor->id,
            ]);

            // Lost/Stolen: one asset_loss approval writes the asset off (LossService), instead
            // of a transfer approval that would only flip a status.
            if (in_array($movementType->code, self::LOSS_TYPE_CODES, true)) {
                $loss = app(LossService::class)->request(
                    $asset, strtolower($movementType->code), $data['notes'] ?? null, $actor, $movement,
                );
                $movement->update(['approval_request_id' => $loss->approval_request_id]);

                return $movement;
            }

            $approvalRequest = $this->workflows->submit($movement, 'transfer', $actor);
            $movement->update(['approval_request_id' => $approvalRequest->id]);

            return $movement;
        });
    }

    /**
     * Bulk multi-select submission (P9.1 — one approval for the whole batch). Creates the
     * child asset_movements rows up front so the approver inbox and per-asset movement
     * tabs already show "pending" entries; apply() of each one waits for applyBulk().
     *
     * The whole thing stays in one transaction so a WorkflowService::submit() failure never
     * leaves an orphaned batch (CLAUDE.md orphan-guard). buildBatch()'s inner transaction is
     * a savepoint under this outer one, so an outer failure still rolls the batch back.
     */
    public function submitBatch(Collection $assets, array $data, User $actor): AssetMovementBatch
    {
        return DB::transaction(function () use ($assets, $data, $actor) {
            $batch = $this->buildBatch($assets, $data, $actor);

            $approvalRequest = $this->workflows->submit($batch, 'transfer', $actor);
            $batch->update(['approval_request_id' => $approvalRequest->id]);

            return $batch;
        });
    }

    /**
     * Create an AssetMovementBatch + its child asset_movements WITHOUT submitting a workflow.
     * Shared by submitBatch() (transfer approval) and M17's KitAssignmentService, which submits
     * the owning kit_assignment to the 'kit_assignment' workflow instead. Pass $kitAssignmentId
     * to tag the batch so applyBulk() denormalizes it onto each movement on approval.
     */
    public function buildBatch(Collection $assets, array $data, User $actor, ?int $kitAssignmentId = null): AssetMovementBatch
    {
        $movementType = MovementType::findOrFail($data['movement_type_id']);

        if (in_array($movementType->code, self::LOSS_TYPE_CODES, true)) {
            throw ValidationException::withMessages([
                'movement_type_id' => 'Lost / Stolen must be reported per asset (each one is a separate write-off).',
            ]);
        }

        foreach ($assets as $asset) {
            $this->assertMovable($asset, $movementType, $data);
        }

        return DB::transaction(function () use ($assets, $data, $movementType, $actor, $kitAssignmentId) {
            $batch = AssetMovementBatch::create([
                'movement_type_id'  => $movementType->id,
                'to_company_id'     => $data['to_company_id'] ?? null,
                'to_location_id'    => $data['to_location_id'] ?? null,
                'to_custodian_id'   => $data['to_custodian_id'] ?? null,
                'to_department_id'  => $data['to_department_id'] ?? null,
                'to_status_id'      => $data['to_status_id'] ?? null,
                'kit_assignment_id' => $kitAssignmentId,
                'status'            => 'pending_approval',
                'notes'             => $data['notes'] ?? null,
                'requested_by'      => $actor->id,
            ]);

            foreach ($assets as $asset) {
                AssetMovement::create([
                    'asset_id'           => $asset->id,
                    'movement_type_id'   => $movementType->id,
                    'batch_id'           => $batch->id,
                    'from_company_id'    => $asset->company_id,
                    'to_company_id'      => $data['to_company_id'] ?? null,
                    'from_location_id'   => $asset->location_id,
                    'to_location_id'     => $data['to_location_id'] ?? null,
                    'from_custodian_id'  => $asset->custodian_id,
                    'to_custodian_id'    => $data['to_custodian_id'] ?? null,
                    'from_department_id' => $asset->department_id,
                    'to_department_id'   => $data['to_department_id'] ?? null,
                    'to_status_id'       => $data['to_status_id'] ?? null,
                    'status'             => 'pending_approval',
                    'requested_by'       => $actor->id,
                ]);
            }

            return $batch;
        });
    }

    /**
     * For an approver: what the destination already holds in the same category(ies) as the
     * assets being moved — for the named PERSON and for the named LOCATION, each when the
     * movement names one (a person first). Works for a single movement, a bulk batch, or a
     * kit assignment (its batch in "single" mode, its own movements in "per asset" mode).
     * Excludes the assets being moved and drafts/retired assets.
     *
     * @return Collection<int, array{category: ?\App\Models\AssetCategory, kind: string, holder: string, total: int, assets: Collection}>
     */
    public function existingHoldingsFor(AssetMovement|AssetMovementBatch|\App\Models\KitAssignment $subject): \Illuminate\Support\Collection
    {
        $movements = match (true) {
            $subject instanceof AssetMovementBatch => $subject->movements()->with('asset')->get(),
            $subject instanceof \App\Models\KitAssignment => $subject->batch
                ? $subject->batch->movements()->with('asset')->get()
                : $subject->movements()->with('asset')->get(),
            default => collect([$subject->loadMissing('asset')]),
        };

        $first = $movements->first();
        if (! $first) {
            return collect();
        }

        // Who/where the assets are going: the person and/or the place, both when both are named.
        $targets = collect();
        if ($first->to_custodian_id) {
            $targets->push([
                'kind'   => 'person',
                'column' => 'custodian_id',
                'id'     => $first->to_custodian_id,
                'holder' => \App\Models\Employee::find($first->to_custodian_id)?->name ?? 'the custodian',
            ]);
        }
        if ($first->to_location_id) {
            $targets->push([
                'kind'   => 'location',
                'column' => 'location_id',
                'id'     => $first->to_location_id,
                'holder' => \App\Models\Location::find($first->to_location_id)?->name ?? 'the location',
            ]);
        }

        if ($targets->isEmpty()) {
            return collect();
        }

        $movingIds = $movements->pluck('asset_id')->all();
        $excludedStatusIds = \App\Models\AssetStatus::whereIn('code', ['DRAFT', ...\App\Models\AssetStatus::TERMINAL])->pluck('id');
        $categoryIds = $movements->pluck('asset.category_id')->filter()->unique()->values();

        return $targets->flatMap(fn (array $target) => $categoryIds->map(function (int $categoryId) use ($target, $movingIds, $excludedStatusIds) {
            $query = Asset::query()
                ->where('category_id', $categoryId)
                ->where($target['column'], $target['id'])
                ->whereNotIn('id', $movingIds)
                ->whereNotIn('status_id', $excludedStatusIds);

            return [
                'category' => \App\Models\AssetCategory::find($categoryId),
                'kind'     => $target['kind'],
                'holder'   => $target['holder'],
                'total'    => (clone $query)->count(),
                'assets'   => $query->with('status')->orderBy('name')->limit(25)->get(),
            ];
        }))->values();
    }

    /** Called by ApplyAssetMovement (the ApprovalRequestApproved listener) for a standalone movement. */
    public function apply(AssetMovement $movement): void
    {
        // Approval is system-level work: company isolation must not hide the asset from the
        // approver's request (e.g. the receiving side approving an inter-company transfer).
        CompanyVisibility::unrestricted(function () use ($movement) {
            DB::transaction(fn () => $this->dispatchOrApply($movement));

            $this->notifyAfterApproval($movement->fresh(['toCustodian.user', 'toLocation.manager.user', 'asset', 'movementType']));
        });
    }

    /**
     * Called by ApplyAssetMovement for a batch (and M17 kit assignment). Atomic across every
     * asset — either the whole bulk move is dispatched/applied or none of it is.
     */
    public function applyBulk(AssetMovementBatch $batch): void
    {
        CompanyVisibility::unrestricted(function () use ($batch) {
            $batch->load('movements.asset', 'movements.movementType');
            $anyTransit = false;

            DB::transaction(function () use ($batch, &$anyTransit) {
                foreach ($batch->movements as $movement) {
                    $movement->kit_assignment_id = $batch->kit_assignment_id;
                    $anyTransit = $this->dispatchOrApply($movement) || $anyTransit;
                }

                $batch->update([
                    'status'         => 'completed',
                    'receipt_status' => $anyTransit ? 'in_transit' : null,
                    'dispatched_at'  => $anyTransit ? now() : null,
                ]);
            });

            foreach ($batch->movements as $movement) {
                $this->notifyAfterApproval($movement->fresh(['toCustodian.user', 'toLocation.manager.user', 'asset', 'movementType']));
            }
        });
    }

    /** Does this movement have a physical receipt step? */
    public function isTransit(AssetMovement $movement): bool
    {
        $code = $movement->movementType?->code;

        if (! in_array($code, self::TRANSIT_TYPE_CODES, true)) {
            return false;
        }

        // A Transfer that only re-files the asset under another department moves nothing.
        return $code !== 'TRANSFER' || $movement->to_location_id !== null;
    }

    /**
     * Approval-time half: physical moves are dispatched (IN_TRANSIT, nothing else changes);
     * everything else applies immediately. Returns true when the movement went in transit.
     */
    private function dispatchOrApply(AssetMovement $movement): bool
    {
        $asset = Asset::withoutGlobalScopes()->findOrFail($movement->asset_id);

        if (! $this->isTransit($movement)) {
            $this->applyToAsset($asset, $movement);
            $movement->fill(['status' => 'completed'])->save();

            return false;
        }

        $inTransitId = $this->inTransitStatusId();

        $movement->fill([
            'status'             => 'completed',
            'receipt_status'     => 'in_transit',
            'dispatched_at'      => now(),
            'previous_status_id' => $asset->status_id,
        ])->save();

        $this->setStatus($asset, $inTransitId, $movement->requested_by, ($movement->movementType->name ?? 'Movement').' approved — in transit');

        return true;
    }

    // ------------------------------------------------------------------ receipt

    /**
     * Who may confirm receipt: the destination location's manager (their linked login), or any
     * movement.verify holder — stores/admin, who also receive on behalf of anyone without a login.
     */
    public function canReceive(User $user, AssetMovement $movement): bool
    {
        if ($user->can('movement.verify')) {
            return true;
        }

        $managerUserId = $movement->toLocation?->manager?->user_id;

        return $managerUserId !== null && (int) $managerUserId === (int) $user->id;
    }

    /** People to tell "this is on its way to you": the location manager, else stores/admins. */
    public function receiversFor(AssetMovement $movement): \Illuminate\Support\Collection
    {
        $manager = $movement->toLocation?->manager?->user;

        if ($manager && $manager->is_active) {
            return collect([$manager]);
        }

        return $this->verifiers();
    }

    /** Does the scanned/typed value identify this asset (pool tag, a /scan/{n} URL, or Asset ID)? */
    public function tagMatches(?Asset $asset, string $raw): bool
    {
        $scanned = trim($raw);
        if (preg_match('#/scan/([^/?\#]+)#', $scanned, $m)) {
            $scanned = urldecode($m[1]);
        }

        return collect([$asset?->activeTag()?->tag_number, $asset?->asset_tag])
            ->filter()
            ->contains(fn ($t) => strcasecmp($t, $scanned) === 0);
    }

    /**
     * Confirm receipt of an in-transit movement. ok/damaged → the move lands now;
     * incomplete/missing → reported short for an admin to resolve.
     *
     * @param array{condition?: ?string, notes?: ?string, photos?: string[], method?: string} $data
     */
    public function receive(AssetMovement $movement, User $actor, array $data): AssetMovement
    {
        if (! $movement->isInTransit()) {
            throw ValidationException::withMessages([
                'movement' => 'This movement is not awaiting receipt.',
            ]);
        }

        $condition = $data['condition'] ?? 'ok';

        if (in_array($condition, ['incomplete', 'missing'], true)) {
            if (blank($data['notes'] ?? null)) {
                throw ValidationException::withMessages([
                    'verification_notes' => 'Describe what is missing or incomplete.',
                ]);
            }

            return $this->reportShort($movement, $condition, $actor, $data['notes'], $data['photos'] ?? []);
        }

        return CompanyVisibility::unrestricted(fn () => DB::transaction(fn () => $this->finalizeReceipt(
            $movement, $actor, $condition, $data['notes'] ?? null, $data['photos'] ?? [], $data['method'] ?? 'scan',
        )));
    }

    /**
     * Report a movement short. `missing` = never arrived (a batch member not scanned);
     * `incomplete` = arrived without parts/accessories. The asset stays IN_TRANSIT with its old
     * custodian/location until an admin resolves it.
     */
    public function reportShort(AssetMovement $movement, string $type, User $actor, ?string $notes, array $photos = []): AssetMovement
    {
        if (! $movement->isInTransit()) {
            throw ValidationException::withMessages(['movement' => 'Only an in-transit movement can be reported short.']);
        }

        $movement->update([
            'receipt_status'         => 'short',
            'short_type'             => $type === 'incomplete' ? 'incomplete' : 'missing',
            'short_reported_at'      => now(),
            'short_reported_by'      => $actor->id,
            'verification_condition' => $type === 'incomplete' ? 'incomplete' : 'missing',
            'verification_notes'     => $notes,
            'verification_photos'    => $photos ?: null,
        ]);

        $this->refreshBatchReceipt($movement->batch);

        $recipients = $this->verifiers()
            ->merge(array_filter([$movement->requestedBy]))
            ->unique('id');

        $this->notifications->sendMany($recipients, 'movement.short_received', [
            'asset'     => Asset::labelFor($movement->asset),
            'short'     => $movement->short_type === 'incomplete' ? 'received incomplete' : 'not received',
            'notes'     => $notes,
            'url'       => route('movements.short.index'),
        ]);

        return $movement;
    }

    /**
     * Batch receipt "finish": every member still in transit (not scanned) is reported missing.
     *
     * @return int number of members reported short
     */
    public function finishBatchReceipt(AssetMovementBatch $batch, User $actor, ?string $notes = null): int
    {
        $pending = $batch->movements()->where('receipt_status', 'in_transit')->get();

        foreach ($pending as $movement) {
            $this->reportShort($movement, 'missing', $actor, $notes ?: 'Not received with the rest of the batch.');
        }

        return $pending->count();
    }

    /**
     * Admin resolution of a short-received movement:
     *   received_later     → the asset turned up; the move lands now.
     *   accepted_as_is     → keep it incomplete (note recorded); the move lands now.
     *   returned_to_sender → cancel the move for this asset; previous status restored.
     *   lost               → raise a loss write-off (asset_loss approval); closes on approval.
     */
    public function resolveShort(AssetMovement $movement, string $resolution, User $admin, ?string $notes): AssetMovement
    {
        if (! $movement->isShort()) {
            throw ValidationException::withMessages(['movement' => 'This movement has no open short-received issue.']);
        }

        if (! array_key_exists($resolution, AssetMovement::RESOLUTIONS)) {
            throw ValidationException::withMessages(['resolution' => 'Pick a resolution.']);
        }

        if (blank($notes)) {
            throw ValidationException::withMessages(['resolution_notes' => 'Add a note explaining the resolution.']);
        }

        return CompanyVisibility::unrestricted(fn () => DB::transaction(function () use ($movement, $resolution, $admin, $notes) {
            $stamp = ['resolution' => $resolution, 'resolution_notes' => $notes, 'resolved_at' => now(), 'resolved_by' => $admin->id];

            switch ($resolution) {
                case 'received_later':
                    $movement->fill($stamp);
                    $this->finalizeReceipt($movement, $admin, 'ok', $notes, [], 'manual');
                    break;

                case 'accepted_as_is':
                    $movement->fill($stamp);
                    $this->finalizeReceipt($movement, $admin, 'incomplete', $movement->verification_notes, $movement->verification_photos ?? [], 'manual');
                    break;

                case 'returned_to_sender':
                    $asset = Asset::withoutGlobalScopes()->findOrFail($movement->asset_id);
                    $this->setStatus($asset, $movement->previous_status_id, $admin->id, 'Short received — returned to sender');
                    $movement->update($stamp + ['receipt_status' => 'returned']);
                    $this->refreshBatchReceipt($movement->batch);
                    break;

                case 'lost':
                    // Stays `short` until the write-off is approved (ApplyAssetLoss closes it).
                    $movement->update($stamp);
                    app(LossService::class)->request(
                        Asset::withoutGlobalScopes()->findOrFail($movement->asset_id), 'lost', $notes, $admin, $movement,
                    );
                    break;
            }

            $this->notifications->sendMany(
                collect(array_filter([$movement->requestedBy, $movement->shortReportedBy]))->unique('id'),
                'movement.short_resolved',
                [
                    'asset'      => Asset::labelFor($movement->asset),
                    'resolution' => AssetMovement::RESOLUTIONS[$resolution],
                    'url'        => route('assets.show', $movement->asset_id),
                ],
            );

            return $movement->fresh();
        }));
    }

    /** The move actually lands: destination applied, status set, receipt stamped. */
    private function finalizeReceipt(AssetMovement $movement, User $actor, string $condition, ?string $notes, array $photos, string $method): AssetMovement
    {
        $asset = Asset::withoutGlobalScopes()->findOrFail($movement->asset_id);

        $this->applyToAsset($asset, $movement, receipt: true, actorId: $actor->id);

        $movement->fill([
            'receipt_status'         => 'received',
            'received_at'            => now(),
            'received_by'            => $actor->id,
            'receipt_method'         => $method,
            'verified_at'            => now(),
            'verified_by'            => $actor->id,
            'verification_condition' => $condition,
            'verification_notes'     => $notes,
            'verification_photos'    => $photos ?: null,
        ])->save();

        $this->refreshBatchReceipt($movement->batch);
        $this->notifyCustodian($movement->fresh(['toCustodian.user', 'asset', 'movementType']));

        return $movement;
    }

    /** Batch receipt state: closed once nothing is in transit or short; partial in between. */
    public function refreshBatchReceipt(?AssetMovementBatch $batch): void
    {
        if (! $batch || ! $batch->receipt_status) {
            return;
        }

        $open = $batch->movements()->whereIn('receipt_status', ['in_transit', 'short'])->count();
        $done = $batch->movements()->whereIn('receipt_status', ['received', 'lost', 'returned'])->count();

        $batch->update(match (true) {
            $open === 0 => ['receipt_status' => 'closed', 'closed_at' => now()],
            $done > 0   => ['receipt_status' => 'partial'],
            default     => ['receipt_status' => 'in_transit'],
        });
    }

    /** Stores/admins who receive and resolve (movement.verify). Empty if the permission isn't seeded. */
    private function verifiers(): \Illuminate\Support\Collection
    {
        try {
            return User::permission('movement.verify')->where('is_active', true)->get();
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return collect();
        }
    }

    private function inTransitStatusId(): int
    {
        return $this->statusIdByCode['IN_TRANSIT'] ??= AssetStatus::firstOrCreate(
            ['code' => 'IN_TRANSIT'],
            ['name' => 'In Transit', 'color' => '#f97316', 'is_system' => true, 'is_active' => true],
        )->id;
    }

    private function setStatus(Asset $asset, ?int $statusId, ?int $actorId, string $reason): void
    {
        if (! $statusId || (int) $statusId === (int) $asset->status_id) {
            return;
        }

        AssetStatusHistory::create([
            'asset_id'       => $asset->id,
            'from_status_id' => $asset->status_id,
            'to_status_id'   => $statusId,
            'changed_by'     => $actorId,
            'reason'         => $reason,
            'created_at'     => now(),
        ]);

        $asset->update(['status_id' => $statusId]);
    }

    private function notifyAfterApproval(AssetMovement $movement): void
    {
        if ($movement->receipt_status !== 'in_transit') {
            $this->notifyCustodian($movement);

            return;
        }

        $this->notifications->sendMany($this->receiversFor($movement), 'movement.in_transit', [
            'asset'         => Asset::labelFor($movement->asset),
            'movement_type' => $movement->movementType?->name,
            'destination'   => $movement->toLocation?->name ?? $movement->toCompany?->name ?? 'stores',
            'url'           => route('movements.receive.form', $movement),
        ]);
    }

    /**
     * Withdraw a still-pending movement — the requester (or an admin) cancels before approval.
     * Distinct from rejection (an approver's decision): marks the approval request withdrawn and
     * the movement 'cancelled'. For a batch member, the whole batch is cancelled since one
     * approval covers it. Does NOT fire ApprovalRequestRejected (that would set 'rejected').
     */
    public function cancel(AssetMovement $movement, User $actor): void
    {
        if ($movement->status !== 'pending_approval') {
            throw ValidationException::withMessages([
                'movement' => 'Only a pending movement can be cancelled.',
            ]);
        }

        $batch = $movement->batch;
        $approvalRequestId = $batch?->approval_request_id ?? $movement->approval_request_id;

        DB::transaction(function () use ($movement, $batch, $approvalRequestId, $actor) {
            if ($approvalRequestId && $request = \App\Models\ApprovalRequest::find($approvalRequestId)) {
                if ($request->status === 'pending') {
                    \App\Models\ApprovalAction::create([
                        'request_id' => $request->id,
                        'step_level' => $request->current_step,
                        'user_id'    => $actor->id,
                        'action'     => 'reject',
                        'comment'    => 'Cancelled by ' . $actor->name,
                        'created_at' => now(),
                    ]);
                    $request->update(['status' => 'rejected']);
                }
            }

            // Keep status='rejected' (the movement didn't apply) but stamp cancelled_at so the UI
            // shows "Cancelled" and distinguishes a self-withdrawal from an approver rejection.
            $stamp = ['status' => 'rejected', 'cancelled_at' => now(), 'cancelled_by' => $actor->id];

            if ($batch) {
                $batch->update($stamp);
                $batch->movements()->update($stamp);
            } else {
                $movement->update($stamp);
            }
        });
    }

    /**
     * Post-completion sign-off — distinct from approval, records who confirmed the move actually
     * happened, plus the receipt condition (ok/damaged/missing), notes and photos. The condition
     * is record-only: it never changes the asset's status (mirrors M10 audit findings).
     */
    public function verify(
        AssetMovement $movement,
        User $actor,
        ?string $condition = null,
        ?string $notes = null,
        array $photoPaths = [],
    ): AssetMovement {
        if ($movement->status !== 'completed') {
            throw ValidationException::withMessages([
                'movement' => 'Only completed movements can be verified.',
            ]);
        }

        $movement->update([
            'verified_at'            => now(),
            'verified_by'            => $actor->id,
            'verification_condition' => $condition,
            'verification_notes'     => $notes,
            'verification_photos'    => $photoPaths ?: null,
        ]);

        return $movement;
    }

    /**
     * Which asset columns a movement type touches. Return explicitly clears the custodian
     * rather than reading to_custodian_id, since "returned" means back to the pool, not
     * reassigned to whoever the form happened to submit.
     */
    private function applyToAsset(Asset $asset, AssetMovement $movement, bool $receipt = false, ?int $actorId = null): void
    {
        $code = $movement->movementType->code;

        $updates = match ($code) {
            'ASSIGNMENT', 'CUSTODIAN_CHANGE' => ['custodian_id' => $movement->to_custodian_id],
            'RETURN' => array_filter(['custodian_id' => null, 'location_id' => $movement->to_location_id], fn ($v, $k) => $k === 'custodian_id' || $v !== null, ARRAY_FILTER_USE_BOTH),
            // A relocation may also hand the asset to a new custodian; each destination is
            // optional (null = leave that attribute unchanged), so only set what was provided.
            'TRANSFER' => array_filter([
                'location_id'   => $movement->to_location_id,
                'department_id' => $movement->to_department_id,
                'custodian_id'  => $movement->to_custodian_id,
            ], fn ($value) => $value !== null),
            'INTER_COMPANY_TRANSFER' => ['company_id' => $movement->to_company_id],
            default => [],
        };

        // A physical move that lands without a named recipient ends Available at its new home,
        // so the previous person's custody ends with it.
        if ($receipt && in_array($code, ['TRANSFER', 'INTER_COMPANY_TRANSFER'], true) && ! $movement->to_custodian_id) {
            $updates['custodian_id'] = null;
        }

        if ($updates) {
            $asset->update($updates);
        }

        // M16: an inter-company transfer is a disposal-for-seller / acquisition-for-buyer, so
        // the old schedule stops and a fresh one starts for the receiver at net book value.
        if ($code === 'INTER_COMPANY_TRANSFER') {
            $this->depreciation->resetForTransfer($asset, now());
        }

        // The movement's explicit to_status_id wins; otherwise assignment/return imply a status
        // (ASSIGNMENT/CUSTODIAN_CHANGE -> ASSIGNED, RETURN -> AVAILABLE) so an approved assignment
        // no longer leaves the asset showing "Available".
        // On receipt a physical move ends Assigned when it names a recipient, else Available.
        $targetStatusId = $movement->to_status_id
            ?? ($receipt
                ? $this->statusId($movement->to_custodian_id ? 'ASSIGNED' : 'AVAILABLE')
                : $this->defaultStatusIdForType($code));

        $this->setStatus(
            $asset,
            $targetStatusId,
            $actorId ?? $movement->requested_by,
            ($movement->movementType->name ?? 'Movement') . ($receipt ? ' received' : ' movement'),
        );
    }

    private function statusId(string $code): ?int
    {
        return $this->statusIdByCode[$code] ??= AssetStatus::where('code', $code)->value('id');
    }

    /** Status a movement type moves the asset into by default (null = leave status untouched). */
    private function defaultStatusIdForType(string $code): ?int
    {
        $statusCode = match ($code) {
            'ASSIGNMENT', 'CUSTODIAN_CHANGE' => 'ASSIGNED',
            'RETURN' => 'AVAILABLE',
            default => null,
        };

        if (! $statusCode) {
            return null;
        }

        return $this->statusIdByCode[$statusCode]
            ??= AssetStatus::where('code', $statusCode)->value('id');
    }

    private function assertMovable(Asset $asset, MovementType $movementType, array $data): void
    {
        // A movement may not smuggle in a status that has its own workflow (Scrapped, Sold,
        // Lost, Draft, In Transit…) — the HTTP layer no longer accepts to_status_id at all; this
        // guards internal callers too.
        if (! empty($data['to_status_id'])
            && in_array(AssetStatus::whereKey($data['to_status_id'])->value('code'), AssetStatus::NOT_HAND_PICKABLE, true)) {
            throw ValidationException::withMessages([
                'to_status_id' => 'A movement cannot set that status — it is reached only through its own workflow.',
            ]);
        }

        if ($asset->isDisposed()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is disposed and cannot be moved.",
            ]);
        }

        if ($asset->isRetired()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" has left the register ({$asset->status?->name}) and cannot be moved.",
            ]);
        }

        if ($asset->isInTransit() || $asset->openTransitMovement()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is in transit — it must be received (or its short-received issue resolved) before it can be moved again.",
            ]);
        }

        if ($asset->hasPendingLoss()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" has a loss write-off pending approval.",
            ]);
        }

        if ($asset->hasPendingHandover()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is in a plaza handover awaiting approval.",
            ]);
        }

        $this->assertOwnershipAllows($asset, $movementType, $data);

        if ($asset->isDraft()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is a draft pending creation approval and cannot be moved.",
            ]);
        }

        if ($asset->hasPendingMovement()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" already has a movement pending approval.",
            ]);
        }

        if ($asset->hasPendingDisposal()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" has a disposal request in progress and cannot be moved.",
            ]);
        }

        if ($movementType->code === 'INTER_COMPANY_TRANSFER'
            && (int) ($data['to_company_id'] ?? 0) === (int) $asset->company_id) {
            throw ValidationException::withMessages([
                'to_company_id' => "Select a different company for \"{$asset->name}\"'s inter-company transfer.",
            ]);
        }

        if (in_array($movementType->code, ['ASSIGNMENT', 'CUSTODIAN_CHANGE'], true) && empty($data['to_custodian_id'])) {
            throw ValidationException::withMessages([
                'to_custodian_id' => 'A custodian is required for this movement type.',
            ]);
        }

        if ($movementType->code === 'TRANSFER' && empty($data['to_location_id']) && empty($data['to_department_id'])) {
            throw ValidationException::withMessages([
                'to_location_id' => 'Provide a new location or department for a transfer.',
            ]);
        }

        if ($movementType->code === 'INTER_COMPANY_TRANSFER' && empty($data['to_company_id'])) {
            throw ValidationException::withMessages([
                'to_company_id' => 'A destination company is required for an inter-company transfer.',
            ]);
        }
    }

    /**
     * NHAI-owned assets belong to their plaza: custodian, room and lane changes are fine, but
     * they never change company and never leave the plaza (they go back to NHAI only through a
     * handover). And none of our assets can be "transferred" to an external party — that is a
     * sale or a handover.
     */
    private function assertOwnershipAllows(Asset $asset, MovementType $movementType, array $data): void
    {
        $code = $movementType->code;

        if ($code === 'INTER_COMPANY_TRANSFER') {
            if ($asset->isExternallyOwned()) {
                throw ValidationException::withMessages([
                    'to_company_id' => "\"{$asset->name}\" belongs to {$asset->company?->name} and cannot be transferred between companies.",
                ]);
            }

            $to = ! empty($data['to_company_id']) ? \App\Models\Company::find($data['to_company_id']) : null;
            if ($to?->isExternal()) {
                throw ValidationException::withMessages([
                    'to_company_id' => "{$to->name} is an external party — hand assets over through a plaza handover or a sale.",
                ]);
            }
        }

        if ($asset->isExternallyOwned() && in_array($code, ['TRANSFER', 'RETURN'], true)
            && ! empty($data['to_location_id']) && (int) $data['to_location_id'] !== (int) $asset->location_id) {
            throw ValidationException::withMessages([
                'to_location_id' => "\"{$asset->name}\" is NHAI property and stays at its plaza.",
            ]);
        }
    }

    /**
     * The recipient confirms they hold the asset. Only arises when the custodian has a login
     * (AssetMovement::awaitsAcknowledgement()). Allowed for that login, or — e.g. against a
     * signed paper receipt — anyone who can assign assets.
     */
    public function canAcknowledge(User $user, AssetMovement $movement): bool
    {
        if (! $movement->awaitsAcknowledgement()) {
            return false;
        }

        return (int) $movement->toCustodian?->user_id === (int) $user->id || $user->can('movement.assign');
    }

    public function acknowledge(AssetMovement $movement, User $user): AssetMovement
    {
        if (! $this->canAcknowledge($user, $movement)) {
            throw ValidationException::withMessages(['movement' => 'This assignment is not awaiting your acknowledgement.']);
        }

        $movement->update(['acknowledged_at' => now(), 'acknowledged_by' => $user->id]);

        return $movement;
    }

    private function notifyCustodian(AssetMovement $movement): void
    {
        // The custodian is an Employee that may have no login account — only notify when
        // the employee is linked to a user (NotificationService targets a User).
        $user = $movement->toCustodian?->user;

        if ($user) {
            $this->notifications->send($user, 'movement.completed', [
                'asset'         => $movement->asset?->name,
                'movement_type' => $movement->movementType?->name,
                'url'           => route('assets.show', $movement->asset_id),
            ]);
        }
    }
}
