<?php

namespace App\Listeners;

use App\Events\ApprovalRequestApproved;
use App\Models\DepreciationSettingRequest;
use App\Services\DepreciationService;

/**
 * Applies an approved depreciation-settings change (M16). Auto-discovered via the typed
 * handle() parameter — never also register in AppServiceProvider::boot() (CLAUDE.md note).
 *
 * Unlike M13's disposal listener (which only unlocks a manual step), this one applies the
 * change outright: a new active settings row + regenerated schedule.
 */
class ApplyDepreciationSettingChange
{
    public function __construct(private DepreciationService $depreciation)
    {
    }

    public function handle(ApprovalRequestApproved $event): void
    {
        if ($event->request->workflow->module !== 'depreciation') {
            return;
        }

        $approvable = $event->request->approvable;

        if ($approvable instanceof DepreciationSettingRequest) {
            $this->depreciation->applySettingChange($approvable);
        }
    }
}
