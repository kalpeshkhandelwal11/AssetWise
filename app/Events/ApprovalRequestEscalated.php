<?php

namespace App\Events;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalRequestEscalated
{
    use Dispatchable;

    public function __construct(
        public ApprovalRequest $request,
        public ApprovalStep $step,
    ) {
    }
}
