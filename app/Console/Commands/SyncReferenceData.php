<?php

namespace App\Console\Commands;

use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Command;

/**
 * Brings a live install's reference data (permissions, system statuses, default workflows,
 * system companies) up to date after `migrate --force`. Additive and idempotent — see
 * ReferenceDataSeeder. Run on every deploy.
 */
class SyncReferenceData extends Command
{
    protected $signature = 'assetwise:sync-reference-data';

    protected $description = 'Add any missing permissions, statuses, workflows and system masters (safe to re-run)';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]);
        $this->info('Reference data is up to date.');

        return self::SUCCESS;
    }
}
