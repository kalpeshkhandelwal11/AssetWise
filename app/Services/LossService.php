<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetLossRequest;
use App\Models\AssetMovement;
use App\Models\User;
use App\Support\CompanyVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lost / Stolen assets — always through the `asset_loss` approval (user decision): the
 * LOST/STOLEN movement types and the short-received "Mark lost" resolution both land here.
 *
 * On approval the asset is written off in one transaction: a pre-approved WRITE_OFF disposal
 * records proceeds 0 → gain/loss = −net book value and stops depreciation, then the asset
 * moves to LOST ("Lost (Written Off)"). Rejection leaves the asset as it was.
 */
class LossService
{
    public function __construct(
        private readonly WorkflowService $workflows,
        private readonly DisposalService $disposals,
        private readonly NotificationService $notifications,
    ) {
    }

    public function request(Asset $asset, string $reason, ?string $notes, User $actor, ?AssetMovement $movement = null): AssetLossRequest
    {
        if ($asset->hasPendingLoss()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" already has a loss write-off pending approval.",
            ]);
        }

        if ($asset->status?->code === 'LOST' || $asset->isRetired()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" has already left the register.",
            ]);
        }

        // create + submit atomically (CLAUDE.md orphan guard).
        return DB::transaction(function () use ($asset, $reason, $notes, $actor, $movement) {
            $loss = AssetLossRequest::create([
                'asset_id'           => $asset->id,
                'movement_id'        => $movement?->id,
                'reason'             => $reason === 'stolen' ? 'stolen' : 'lost',
                'notes'              => $notes,
                'status'             => 'pending_approval',
                'previous_status_id' => $asset->status_id,
                'requested_by'       => $actor->id,
            ]);

            $approval = $this->workflows->submit($loss, 'asset_loss', $actor);
            $loss->update(['approval_request_id' => $approval->id]);

            return $loss;
        });
    }

    /** ApplyAssetLoss (terminal approval): write the asset off and mark it lost. */
    public function apply(AssetLossRequest $loss, User $approver): void
    {
        CompanyVisibility::unrestricted(function () use ($loss, $approver) {
            DB::transaction(function () use ($loss, $approver) {
                $asset = Asset::withoutGlobalScopes()->findOrFail($loss->asset_id);
                $requester = $loss->requestedBy ?? $approver;
                $label = $loss->reason === 'stolen' ? 'Stolen' : 'Lost';

                $disposal = $this->disposals->createApproved(
                    $asset, 'WRITE_OFF', "{$label} — ".($loss->notes ?: 'asset loss'), $requester, $loss->approval_request_id,
                );
                $this->disposals->writeOff($disposal, ['disposal_value' => 0], $approver);
                $this->disposals->finalize($disposal, $approver, 'LOST', "{$label} — written off");

                $loss->update(['status' => 'approved', 'approved_at' => now(), 'disposal_request_id' => $disposal->id]);

                if ($movement = $loss->movement()->withoutGlobalScopes()->first()) {
                    $movement->update([
                        'status'         => 'completed',
                        'receipt_status' => $movement->receipt_status === 'short' ? 'lost' : $movement->receipt_status,
                    ]);
                    app(MovementService::class)->refreshBatchReceipt($movement->batch);
                }
            });
        });
    }

    /** MarkAssetLossRejected: nothing was changed; reopen a short-received issue for another decision. */
    public function reject(AssetLossRequest $loss): void
    {
        CompanyVisibility::unrestricted(function () use ($loss) {
            DB::transaction(function () use ($loss) {
                $loss->update(['status' => 'rejected']);

                $movement = $loss->movement()->withoutGlobalScopes()->first();

                if (! $movement) {
                    return;
                }

                if ($movement->status === 'pending_approval') {
                    $movement->update(['status' => 'rejected']); // the LOST/STOLEN movement itself
                } elseif ($movement->isShort()) {
                    $movement->update(['resolution' => null, 'resolution_notes' => null, 'resolved_at' => null, 'resolved_by' => null]);
                }
            });
        });
    }
}
