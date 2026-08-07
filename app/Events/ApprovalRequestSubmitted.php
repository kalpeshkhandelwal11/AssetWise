<?php

namespace App\Events;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalRequestSubmitted
{
    use Dispatchable;

    public function __construct(
        public ApprovalRequest $request,
        public User $submittedBy,
    ) {
    }
}
