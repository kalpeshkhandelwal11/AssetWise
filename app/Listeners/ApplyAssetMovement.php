<?php

namespace App\Listeners;

use App\Events\ApprovalRequestApproved;
use App\Models\AssetMovement;
use App\Models\AssetMovementBatch;
use App\Services\MovementService;

/**
 * Auto-discovered via the typed handle() parameter — never also register this in
 * AppServiceProvider::boot() (see CLAUDE.md's LogSuccessfulLogin/ApplyTagReplacement note).
 */
class ApplyAssetMovement
{
    public function __construct(private MovementService $movements)
    {
    }

    public function handle(ApprovalRequestApproved $event): void
    {
        if ($event->request->workflow->module !== 'transfer') {
            return;
        }

        $approvable = $event->request->approvable;

        if ($approvable instanceof AssetMovement) {
            $this->movements->apply($approvable);
        } elseif ($approvable instanceof AssetMovementBatch) {
            $this->movements->applyBulk($approvable);
        }
    }
}
