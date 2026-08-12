<?php

namespace App\Listeners;

use App\Events\ApprovalRequestApproved;
use App\Models\Asset;
use App\Models\AssetStatus;

/**
 * Terminal-approval of a new asset's creation request: promote it out of DRAFT and live.
 *
 * Auto-discovered via the typed handle() parameter — never also register this in
 * AppServiceProvider::boot() (see CLAUDE.md's listener note).
 */
class ApplyAssetCreation
{
    public function handle(ApprovalRequestApproved $event): void
    {
        if ($event->request->workflow->module !== 'asset_creation') {
            return;
        }

        $asset = $event->request->approvable;

        if ($asset instanceof Asset) {
            $available = AssetStatus::where('code', 'AVAILABLE')->first();
            if ($available) {
                $asset->update(['status_id' => $available->id]);
            }
        }
    }
}
