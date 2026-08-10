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

        $disposalRequest->update([
            'disposal_value' => $data['disposal_value'] ?? null,
            'written_off_at' => now(),
            'written_off_by' => $actor->id,
            'status'         => 'written_off',
        ]);

        return $disposalRequest;
    }

    /** Terminal step: locks the asset out of movement/reassignment by flipping its status to Disposed. */
    public function scrap(DisposalRequest $disposalRequest, User $actor): DisposalRequest
    {
        if ($disposalRequest->status !== 'written_off') {
            throw ValidationException::withMessages([
                'status' => 'Only a written-off disposal request can be marked scrapped.',
            ]);
        }

        DB::transaction(function () use ($disposalRequest, $actor) {
            $asset = $disposalRequest->asset;
            $disposedStatus = AssetStatus::where('code', 'DISPOSED')->first();

            if ($disposedStatus && $disposedStatus->id !== $asset->status_id) {
                AssetStatusHistory::create([
                    'asset_id'       => $asset->id,
                    'from_status_id' => $asset->status_id,
                    'to_status_id'   => $disposedStatus->id,
                    'changed_by'     => $actor->id,
                    'reason'         => 'Disposal scrapped',
                    'created_at'     => now(),
                ]);

                $asset->update(['status_id' => $disposedStatus->id]);
            }

            $disposalRequest->update([
                'scrapped_at' => now(),
                'scrapped_by' => $actor->id,
                'status'      => 'scrapped',
            ]);
        });

        if ($disposalRequest->requestedBy) {
            $this->notifications->send($disposalRequest->requestedBy, 'disposal.completed', [
                'asset' => $disposalRequest->asset?->name,
                'url'   => route('disposals.show', $disposalRequest),
            ]);
        }

        return $disposalRequest;
    }

    private function assertDisposable(Asset $asset): void
    {
        if ($asset->isDisposed()) {
            throw ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" is already disposed.",
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
