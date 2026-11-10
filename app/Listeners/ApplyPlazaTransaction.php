<?php

namespace App\Listeners;

use App\Events\ApprovalRequestApproved;
use App\Models\PlazaHandover;
use App\Models\PlazaTakeover;
use App\Services\HandoverService;
use App\Services\TakeoverService;

/**
 * Terminal approval of a toll-plaza takeover or handover (Phase 3).
 * Auto-discovered via the typed handle() — never register in AppServiceProvider.
 */
class ApplyPlazaTransaction
{
    public function __construct(
        private readonly TakeoverService $takeovers,
        private readonly HandoverService $handovers,
    ) {
    }

    public function handle(ApprovalRequestApproved $event): void
    {
        $module = $event->request->workflow->module;
        $approvable = $event->request->approvable;

        if ($module === 'takeover' && $approvable instanceof PlazaTakeover) {
            $this->takeovers->apply($approvable, $event->finalApprover);
        } elseif ($module === 'handover' && $approvable instanceof PlazaHandover) {
            $this->handovers->apply($approvable, $event->finalApprover);
        }
    }
}
