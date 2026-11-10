<?php

namespace App\Listeners;

use App\Events\ApprovalRequestRejected;
use App\Models\AssetLossRequest;
use App\Services\LossService;

/** A rejected loss write-off changes nothing on the asset. Auto-discovered — never register manually. */
class MarkAssetLossRejected
{
    public function __construct(private readonly LossService $losses)
    {
    }

    public function handle(ApprovalRequestRejected $event): void
    {
        if ($event->request->workflow->module !== 'asset_loss') {
            return;
        }

        $loss = AssetLossRequest::withoutGlobalScopes()->find($event->request->approvable_id);

        if ($loss) {
            $this->losses->reject($loss);
        }
    }
}
