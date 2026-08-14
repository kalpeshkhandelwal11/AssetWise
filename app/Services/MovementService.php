<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetMovementBatch;
use App\Models\AssetStatus;
use App\Models\AssetStatusHistory;
use App\Models\MovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assignment / Return / Transfer / Custodian Change / Inter-Company Transfer (M09).
 * Every movement is approval-gated — submit() only opens the request; apply()/applyBulk()
 * run the actual field changes once WorkflowService fires ApprovalRequestApproved.
 */
class MovementService
{
    /** Movement type codes gated by movement.transfer; everything else needs only movement.assign. */
    private const TRANSFER_TYPE_CODES = ['TRANSFER', 'INTER_COMPANY_TRANSFER'];

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

    /** Called by ApplyAssetMovement (the ApprovalRequestApproved listener) for a standalone movement. */
    public function apply(AssetMovement $movement): void
    {
        DB::transaction(function () use ($movement) {
            $this->applyToAsset($movement->asset, $movement);
            $movement->update(['status' => 'completed']);
        });

        $this->notifyCustodian($movement->fresh(['toCustodian.user', 'asset', 'movementType']));
    }

    /**
     * Called by ApplyAssetMovement for a batch. Atomic across every asset in the batch —
     * either the whole bulk move lands or none of it does.
     */
    public function applyBulk(AssetMovementBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            foreach ($batch->movements as $movement) {
                $this->applyToAsset($movement->asset, $movement);
                $movement->update([
                    'status'            => 'completed',
                    'kit_assignment_id' => $batch->kit_assignment_id,
                ]);
            }

            $batch->update(['status' => 'completed']);
        });

        foreach ($batch->movements as $movement) {
            $this->notifyCustodian($movement->fresh(['toCustodian.user', 'asset', 'movementType']));
        }
    }

    /** Post-completion sign-off — distinct from approval, records who confirmed the move actually happened. */
    public function verify(AssetMovement $movement, User $actor): AssetMovement
    {
        if ($movement->status !== 'completed') {
            throw ValidationException::withMessages([
                'movement' => 'Only completed movements can be verified.',
            ]);
        }

        $movement->update(['verified_at' => now(), 'verified_by' => $actor->id]);

        return $movement;
    }

    /**
     * Which asset columns a movement type touches. Return explicitly clears the custodian
     * rather than reading to_custodian_id, since "returned" means back to the pool, not
     * reassigned to whoever the form happened to submit.
     */
    private function applyToAsset(Asset $asset, AssetMovement $movement): void
    {
        $code = $movement->movementType->code;

        $updates = match ($code) {
            'ASSIGNMENT', 'CUSTODIAN_CHANGE' => ['custodian_id' => $movement->to_custodian_id],
            'RETURN' => ['custodian_id' => null],
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
        $targetStatusId = $movement->to_status_id ?? $this->defaultStatusIdForType($code);

        if ($targetStatusId && $targetStatusId !== $asset->status_id) {
            AssetStatusHistory::create([
                'asset_id'       => $asset->id,
                'from_status_id' => $asset->status_id,
                'to_status_id'   => $targetStatusId,
                'changed_by'     => $movement->requested_by,
                'reason'         => ($movement->movementType->name ?? 'Movement') . ' movement',
                'created_at'     => now(),
            ]);

            $asset->update(['status_id' => $targetStatusId]);
        }
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
        if ($asset->isDisposed()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is disposed and cannot be moved.",
            ]);
        }

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
