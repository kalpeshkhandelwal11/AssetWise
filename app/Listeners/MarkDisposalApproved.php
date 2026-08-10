<?php

namespace App\Listeners;

use App\Events\ApprovalRequestApproved;
use App\Models\DisposalRequest;
use App\Services\DisposalService;

/**
 * Auto-discovered via the typed handle() parameter — never also register this in
 * AppServiceProvider::boot() (see CLAUDE.md's LogSuccessfulLogin/ApplyTagReplacement note).
 *
 * Unlike M09's ApplyAssetMovement, this does not touch the asset — approval only unlocks
 * the write-off action, a separate manual step.
 */
class MarkDisposalApproved
{
    public function __construct(private DisposalService $disposals)
    {
    }

    public function handle(ApprovalRequestApproved $event): void
    {
        if ($event->request->workflow->module !== 'disposal') {
            return;
        }

        $approvable = $event->request->approvable;

        if ($approvable instanceof DisposalRequest) {
            $this->disposals->markApproved($approvable);
        }
    }
}
