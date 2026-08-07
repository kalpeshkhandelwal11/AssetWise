<?php

namespace App\Console\Commands;

use App\Services\WorkflowService;
use Illuminate\Console\Command;

class EscalateApprovals extends Command
{
    protected $signature = 'approvals:escalate';

    protected $description = 'Escalate pending approval steps that have been open longer than their configured escalation_hours';

    public function handle(WorkflowService $workflows): int
    {
        $count = $workflows->escalate();

        $this->info($count === 0
            ? 'No approval requests were due for escalation.'
            : "Escalated {$count} approval request(s).");

        return self::SUCCESS;
    }
}
