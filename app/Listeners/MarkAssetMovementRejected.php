<?php

namespace App\Listeners;

use App\Events\ApprovalRequestRejected;
use App\Models\AssetMovement;
use App\Models\AssetMovementBatch;

/**
 * Flips the movement/batch row to 'rejected' so Asset::hasPendingMovement() stops
 * blocking new submissions once a request is turned down. Unlike TagReplacementRequest
 * (which leaves its own status untouched on rejection — nothing else reads it), M09's
 * "pending movement" guard reads asset_movements.status directly, so it must track the
 * ApprovalRequest's outcome or every rejected asset would be permanently stuck.
 *
 * Auto-discovered via the typed handle() parameter — never also register in
 * AppServiceProvider::boot().
 */
class MarkAssetMovementRejected
{
    public function handle(ApprovalRequestRejected $event): void
    {
        if ($event->request->workflow->module !== 'transfer') {
            return;
        }

        $approvable = $event->request->approvable;

        if ($approvable instanceof AssetMovement) {
            $approvable->update(['status' => 'rejected']);
        } elseif ($approvable instanceof AssetMovementBatch) {
            $approvable->update(['status' => 'rejected']);
            $approvable->movements()->update(['status' => 'rejected']);
        }
    }
}
