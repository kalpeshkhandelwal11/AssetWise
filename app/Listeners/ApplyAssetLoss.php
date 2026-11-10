<?php

namespace App\Listeners;

use App\Events\ApprovalRequestApproved;
use App\Models\AssetLossRequest;
use App\Services\LossService;

/**
 * Terminal approval of an `asset_loss` request: write the asset off and mark it
 * "Lost (Written Off)". Auto-discovered via the typed handle() — never register manually.
 */
class ApplyAssetLoss
{
    public function __construct(private readonly LossService $losses)
    {
    }

    public function handle(ApprovalRequestApproved $event): void
    {
        if ($event->request->workflow->module !== 'asset_loss') {
            return;
        }

        $loss = AssetLossRequest::withoutGlobalScopes()->find($event->request->approvable_id);

        if ($loss) {
            $this->losses->apply($loss, $event->finalApprover);
        }
    }
}
