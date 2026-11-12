<?php

namespace App\Console\Commands;

use App\Services\MaintenanceReminderService;
use Illuminate\Console\Command;

/** Daily, mirrors alerts:expiry — safe to run by hand; each threshold fires on its own day only. */
class SendMaintenanceReminders extends Command
{
    protected $signature = 'maintenance:remind';

    protected $description = 'Notify maintenance.manage users and custodians of scheduled maintenance due in 7 or 1 day(s), or overdue since yesterday';

    public function handle(MaintenanceReminderService $reminders): int
    {
        $count = $reminders->run();

        $this->info($count === 0
            ? 'No maintenance reminders were due today.'
            : "Sent {$count} maintenance reminder(s).");

        return self::SUCCESS;
    }
}
