<?php

namespace App\Listeners;

use App\Events\ApprovalRequestRejected;
use App\Models\DisposalRequest;

/**
 * Flips the disposal request to 'rejected' so Asset::hasPendingDisposal() stops blocking
 * new movement/disposal submissions once a request is turned down — same reasoning as
 * M09's MarkAssetMovementRejected.
 *
 * Auto-discovered via the typed handle() parameter — never also register in
 * AppServiceProvider::boot().
 */
class MarkDisposalRejected
{
    public function handle(ApprovalRequestRejected $event): void
    {
        if ($event->request->workflow->module !== 'disposal') {
            return;
        }

        $approvable = $event->request->approvable;

        if ($approvable instanceof DisposalRequest) {
            $approvable->update(['status' => 'rejected']);
        }
    }
}
