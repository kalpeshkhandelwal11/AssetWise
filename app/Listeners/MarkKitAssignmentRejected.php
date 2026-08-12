<?php

namespace App\Listeners;

use App\Events\ApprovalRequestRejected;
use App\Models\KitAssignment;
use App\Services\KitAssignmentService;

/**
 * Flips a rejected single-mode kit assignment (and its batch/movements) to 'rejected' so the
 * assets are freed for resubmission — same pattern as MarkDisposalRejected. Auto-discovered
 * via the typed handle() parameter; never also register in AppServiceProvider::boot().
 */
class MarkKitAssignmentRejected
{
    public function __construct(private KitAssignmentService $assignments)
    {
    }

    public function handle(ApprovalRequestRejected $event): void
    {
        if ($event->request->workflow->module !== 'kit_assignment') {
            return;
        }

        $approvable = $event->request->approvable;

        if ($approvable instanceof KitAssignment) {
            $this->assignments->markRejected($approvable);
        }
    }
}
