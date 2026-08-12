<?php

namespace App\Listeners;

use App\Events\ApprovalRequestRejected;
use App\Models\Asset;

/**
 * A rejected creation request leaves the asset in DRAFT. The request itself is now terminal,
 * so hasPendingCreationApproval() returns false and the owner can edit and resubmit it.
 *
 * Auto-discovered via the typed handle() parameter — never also register in
 * AppServiceProvider::boot().
 */
class MarkAssetCreationRejected
{
    public function handle(ApprovalRequestRejected $event): void
    {
        if ($event->request->workflow->module !== 'asset_creation') {
            return;
        }

        // No state change needed — the asset stays DRAFT and the (now-rejected) request no
        // longer counts as pending. This listener exists so the intent is explicit and so
        // future side-effects (e.g. notifying the creator) have a home.
    }
}
