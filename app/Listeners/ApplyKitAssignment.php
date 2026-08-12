<?php

namespace App\Listeners;

use App\Events\ApprovalRequestApproved;
use App\Models\KitAssignment;
use App\Services\KitAssignmentService;

/**
 * Applies an approved single-mode kit assignment/return (M17) — moves every asset in the
 * batch via MovementService::applyBulk(). Auto-discovered via the typed handle() parameter;
 * never also register in AppServiceProvider::boot() (CLAUDE.md note).
 *
 * per_asset mode needs no listener here: each asset is its own 'transfer' request handled by
 * M09's ApplyAssetMovement.
 */
class ApplyKitAssignment
{
    public function __construct(private KitAssignmentService $assignments)
    {
    }

    public function handle(ApprovalRequestApproved $event): void
    {
        if ($event->request->workflow->module !== 'kit_assignment') {
            return;
        }

        $approvable = $event->request->approvable;

        if ($approvable instanceof KitAssignment) {
            $this->assignments->applyBatch($approvable);
        }
    }
}
