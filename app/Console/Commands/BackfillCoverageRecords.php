<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\AssetCoverageSync;
use Illuminate\Console\Command;

/**
 * Catch-up for assets whose warranty / AMC expiry was typed on the asset (form, import or
 * takeover) before AssetCoverageSync existed: turns each such date into the asset's first
 * warranty record / AMC contract, so it shows in the AMC & Warranty lists and report.
 * Idempotent (an asset that already has a record of that kind is skipped), so it is safe to run
 * on every deploy.
 */
class BackfillCoverageRecords extends Command
{
    protected $signature = 'assets:backfill-coverage {--dry-run : List what would be created without writing}';

    protected $description = 'Create warranty / AMC records for assets that only carry an expiry date';

    public function handle(AssetCoverageSync $coverage): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;

        // System-level catch-up: every company's assets, whoever runs it.
        Asset::withoutGlobalScopes()
            ->where(fn ($q) => $q->whereNotNull('warranty_expiry')->orWhereNotNull('amc_expiry'))
            ->orderBy('id')
            ->chunkById(200, function ($assets) use ($coverage, $dryRun, &$created) {
                foreach ($assets as $asset) {
                    if ($dryRun) {
                        $missing = array_filter([
                            $asset->warranty_expiry && ! $coverage->hasWarrantyRecords($asset) ? 'warranty' : null,
                            $asset->amc_expiry && ! $coverage->hasAmcContracts($asset) ? 'AMC' : null,
                        ]);
                        if ($missing) {
                            $this->line("  [dry-run] asset #{$asset->id} \"{$asset->name}\" -> " . implode(' + ', $missing));
                            $created += count($missing);
                        }
                        continue;
                    }

                    if ($n = $coverage->recordFromAsset($asset)) {
                        $this->line("  asset #{$asset->id} \"{$asset->name}\": {$n} record(s) created");
                        $created += $n;
                    }
                }
            });

        $this->info($created === 0
            ? 'Every expiry date already has its warranty / AMC record.'
            : ($dryRun ? "Would create {$created}" : "Created {$created}") . ' warranty / AMC record(s).');

        return self::SUCCESS;
    }
}
