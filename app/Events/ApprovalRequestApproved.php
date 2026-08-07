<?php

namespace App\Events;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired ONLY on terminal approval (the final step), never on intermediate advances.
 *
 * This is how M08 hands control back to the domain without compile-time knowledge of
 * modules that do not exist yet. Consumers listen and switch on
 * $event->request->workflow->module, acting on $event->request->approvable:
 *
 *   tag_replacement -> TagService::applyReplacement()   (M05)
 *   transfer        -> MovementService                  (M09)
 *   disposal        -> M13
 *   kit_assignment  -> M17
 *
 * Dispatched synchronously (no ShouldQueue) so listeners run inline and the caller sees
 * the completed side effects in the same request/response cycle.
 */
class ApprovalRequestApproved
{
    use Dispatchable;

    public function __construct(
        public ApprovalRequest $request,
        public User $finalApprover,
    ) {
    }
}
