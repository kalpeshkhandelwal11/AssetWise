<?php

namespace App\Console\Commands;

use App\Services\WorkflowService;
use Illuminate\Console\Command;

/**
 * Hourly: e-mails a one-time reminder for every approval that has sat 24h+ on its current
 * step — to that step's approvers and to the person who submitted it. The 48h escalation
 * (approvals:escalate) is unchanged and still runs on top of this.
 */
class RemindApprovals extends Command
{
    protected $signature = 'approvals:remind {--hours=24 : Pending age that triggers the reminder}';

    protected $description = 'Send a one-time reminder for approvals pending longer than 24 hours';

    public function handle(WorkflowService $workflows): int
    {
        $count = $workflows->remind((int) $this->option('hours'));

        $this->info($count === 0
            ? 'No approval requests were due for a reminder.'
            : "Sent reminders for {$count} approval request(s).");

        return self::SUCCESS;
    }
}
