<?php

namespace App\Console\Commands;

use App\Services\ExpiryAlertService;
use Illuminate\Console\Command;

class SendExpiryAlerts extends Command
{
    protected $signature = 'alerts:expiry';

    protected $description = 'Notify maintenance.manage users and asset custodians of warranty/AMC expiries hitting the 30/7/1-day thresholds';

    public function handle(ExpiryAlertService $alerts): int
    {
        $count = $alerts->run();

        $this->info($count === 0
            ? 'No expiry alerts were due today.'
            : "Sent {$count} expiry alert(s).");

        return self::SUCCESS;
    }
}
