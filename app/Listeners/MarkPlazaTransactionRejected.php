<?php

namespace App\Listeners;

use App\Events\ApprovalRequestRejected;
use App\Models\PlazaHandover;
use App\Models\PlazaTakeover;
use App\Services\HandoverService;
use App\Services\TakeoverService;

/** Rejected takeover: its draft assets are removed. Rejected handover: assets unlocked. Auto-discovered. */
class MarkPlazaTransactionRejected
{
    public function __construct(
        private readonly TakeoverService $takeovers,
        private readonly HandoverService $handovers,
    ) {
    }

    public function handle(ApprovalRequestRejected $event): void
    {
        $module = $event->request->workflow->module;
        $approvable = $event->request->approvable;

        if ($module === 'takeover' && $approvable instanceof PlazaTakeover) {
            $this->takeovers->markRejected($approvable);
        } elseif ($module === 'handover' && $approvable instanceof PlazaHandover) {
            $this->handovers->markRejected($approvable);
        }
    }
}
