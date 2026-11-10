<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetStatusHistory;
use App\Models\DisposalRequest;
use App\Models\DisposalType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Disposal request -> approval -> write-off -> scrap (M13). Unlike M09's movements,
 * approval does not apply anything by itself — it only unlocks the write-off action,
 * which is its own manual step, followed by scrap completion.
 */
class DisposalService
{
    public function __construct(
        private WorkflowService $workflows,
        private NotificationService $notifications,
        private DepreciationService $depreciation,
    ) {
    }

    /**
     * Wrapped in a transaction because 'disposal' has no seeded default workflow (M08
     * decision) — WorkflowService::submit() throws until an admin configures one, and
     * without the transaction that would leave an orphaned disposal_requests row behind.
     */
    public function submit(Asset $asset, array $data, User $actor): DisposalRequest
    {
        $this->assertDisposable($asset);
        DisposalType::findOrFail($data['disposal_type_id']);

        return DB::transaction(function () use ($asset, $data, $actor) {
            $disposalRequest = DisposalRequest::create([
                'asset_id'         => $asset->id,
                'disposal_type_id' => $data['disposal_type_id'],
                'reason'           => $data['reason'],
                'status'           => 'pending_approval',
                'requested_by'     => $actor->id,
            ]);

            $approvalRequest = $this->workflows->submit($disposalRequest, 'disposal', $actor);
            $disposalRequest->update(['approval_request_id' => $approvalRequest->id]);

            return $disposalRequest;
        });
    }

    /**
     * A disposal whose approval already happened elsewhere — a loss write-off (asset_loss
     * workflow) or a plaza handover. Created directly as `approved`; no `disposal` workflow is
     * needed because the owning approval IS the disposal approval. Callers then writeOff() and
     * finalize() in the same transaction.
     */
    public function createApproved(Asset $asset, string $typeCode, string $reason, User $requester, ?int $approvalRequestId = null): DisposalRequest
    {
        $type = DisposalType::firstOrCreate(
            ['code' => $typeCode],
            ['name' => \Illuminate\Support\Str::headline(strtolower($typeCode)), 'is_active' => true],
        );

        return DisposalRequest::create([
            'asset_id'            => $asset->id,
            'disposal_type_id'    => $type->id,
            'reason'              => $reason,
            'status'              => 'approved',
            'requested_by'        => $requester->id,
            'approval_request_id' => $approvalRequestId,
        ]);
    }

    /** Called by MarkDisposalApproved (the ApprovalRequestApproved listener) — unlocks write-off, nothing else. */
    public function markApproved(DisposalRequest $disposalRequest): void
    {
        $disposalRequest->update(['status' => 'approved']);
    }

    public function writeOff(DisposalRequest $disposalRequest, array $data, User $actor): DisposalRequest
    {
        if ($disposalRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Only an approved disposal request can be written off.',
            ]);
        }

        $proceeds = isset($data['disposal_value']) ? (float) $data['disposal_value'] : null;

        // M16: freeze depreciation at the disposal date and book the gain/loss on sale
        // (proceeds - net book value). No active depreciation setting → leave the columns null.
        [$netBookValue, $gainLoss] = $this->settleDepreciation($disposalRequest->asset, $proceeds);

        $disposalRequest->update([
            'disposal_value'             => $proceeds,
            'net_book_value_at_disposal' => $netBookValue,
            'gain_loss'                  => $gainLoss,
            'written_off_at'             => now(),
            'written_off_by'             => $actor->id,
            'status'                     => 'written_off',
        ]);

        return $disposalRequest;
    }

    /**
     * @return array{0: float|null, 1: float|null} [netBookValueAtDisposal, gainLoss]
     */
    private function settleDepreciation(Asset $asset, ?float $proceeds): array
    {
        $setting = $asset->activeDepreciationSetting();

        if (! $setting) {
            return [null, null];
        }

        $nbv = $this->depreciation->netBookValue($setting, now());
        $this->depreciation->stop($setting, now());

        return [$nbv, round(($proceeds ?? 0.0) - $nbv, 2)];
    }

    /** Terminal step: locks the asset out of movement/reassignment by flipping its status to Disposed. */
    public function scrap(DisposalRequest $disposalRequest, User $actor): DisposalRequest
    {
        if ($disposalRequest->status !== 'written_off') {
            throw ValidationException::withMessages([
                'status' => 'Only a written-off disposal request can be marked scrapped.',
            ]);
        }

        $this->finalize($disposalRequest, $actor);

        if ($disposalRequest->requestedBy) {
            $this->notifications->send($disposalRequest->requestedBy, 'disposal.completed', [
                'asset' => $disposalRequest->asset?->name,
                'url'   => route('disposals.show', $disposalRequest),
            ]);
        }

        return $disposalRequest;
    }

    /**
     * Close a written-off disposal and move the asset to its terminal status — DISPOSED for a
     * scrap, LOST for a loss write-off, SOLD_EXT / HANDED_OVER for a plaza handover. The
     * disposal row ends `scrapped` (its enum is unchanged); the asset status says why.
     */
    public function finalize(DisposalRequest $disposalRequest, User $actor, string $statusCode = 'DISPOSED', string $reason = 'Disposal scrapped'): DisposalRequest
    {
        DB::transaction(function () use ($disposalRequest, $actor, $statusCode, $reason) {
            $asset = Asset::withoutGlobalScopes()->findOrFail($disposalRequest->asset_id);
            $status = AssetStatus::where('code', $statusCode)->first();

            if ($status && $status->id !== $asset->status_id) {
                AssetStatusHistory::create([
                    'asset_id'       => $asset->id,
                    'from_status_id' => $asset->status_id,
                    'to_status_id'   => $status->id,
                    'changed_by'     => $actor->id,
                    'reason'         => $reason,
                    'created_at'     => now(),
                ]);

                $asset->update(['status_id' => $status->id]);
            }

            $disposalRequest->update([
                'scrapped_at' => now(),
                'scrapped_by' => $actor->id,
                'status'      => 'scrapped',
            ]);
        });

        return $disposalRequest;
    }

    private function assertDisposable(Asset $asset): void
    {
        if ($asset->isRetired() || $asset->status?->code === 'LOST') {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" has already left the register.",
            ]);
        }

        if ($asset->isInTransit()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is in transit — receive it first.",
            ]);
        }

        if ($asset->isExternallyOwned()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" belongs to {$asset->company?->name} — it is returned through a plaza handover, never disposed.",
            ]);
        }

        if ($asset->hasPendingHandover()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is in a plaza handover awaiting approval.",
            ]);
        }

        if ($asset->hasPendingLoss()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" has a loss write-off pending approval.",
            ]);
        }

        if ($asset->isDisposed()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is already disposed.",
            ]);
        }

        if ($asset->isDraft()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is a draft pending creation approval and cannot be disposed.",
            ]);
        }

        if ($asset->hasPendingMovement()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" has a movement pending approval and cannot be disposed.",
            ]);
        }

        if ($asset->hasPendingDisposal()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" already has a disposal request in progress.",
            ]);
        }
    }
}
