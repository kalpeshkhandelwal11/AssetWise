<?php

namespace App\Listeners;

use App\Events\ApprovalRequestRejected;
use App\Models\DepreciationSettingRequest;

/**
 * Flips a depreciation settings change to 'rejected' so Asset::hasPendingDepreciationRequest()
 * stops blocking resubmission once a request is turned down — same reasoning as M09/M13's
 * rejection listeners.
 *
 * Auto-discovered via the typed handle() parameter — never also register in
 * AppServiceProvider::boot().
 */
class MarkDepreciationRequestRejected
{
    public function handle(ApprovalRequestRejected $event): void
    {
        if ($event->request->workflow->module !== 'depreciation') {
            return;
        }

        $approvable = $event->request->approvable;

        if ($approvable instanceof DepreciationSettingRequest) {
            $approvable->update(['status' => 'rejected']);
        }
    }
}
