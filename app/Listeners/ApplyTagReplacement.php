<?php

namespace App\Listeners;

use App\Events\ApprovalRequestApproved;
use App\Services\TagService;

/**
 * Auto-discovered via the typed handle() parameter — never also register this in
 * AppServiceProvider::boot(), the same double-registration mistake CLAUDE.md flags for
 * LogSuccessfulLogin/LogFailedLogin applies identically here.
 */
class ApplyTagReplacement
{
    public function __construct(private TagService $tags)
    {
    }

    public function handle(ApprovalRequestApproved $event): void
    {
        if ($event->request->workflow->module !== 'tag_replacement') {
            return;
        }

        $this->tags->applyReplacement($event->request->approvable);
    }
}
