<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\DepreciationService;
use Illuminate\Console\Command;

/**
 * Auto-create depreciation from the category default for existing assets that should have a
 * schedule but don't — i.e. costed, non-disposed assets in a category with a depreciation
 * default and no active setting yet. One-off catch-up for assets created before auto-create,
 * or imported. Uses the same DepreciationService::applyCategoryDefault() as the live path, so
 * it's idempotent (skips anything already set up).
 */
class BackfillDepreciation extends Command
{
    protected $signature = 'assets:backfill-depreciation {--dry-run : List what would be created without writing}';

    protected $description = 'Create depreciation schedules from category defaults for costed assets that lack one';

    public function handle(DepreciationService $depreciation): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;

        Asset::with(['category.depreciationDefault'])
            ->where('purchase_cost', '>', 0)
            ->whereHas('category.depreciationDefault')
            ->orderBy('id')
            ->chunkById(200, function ($assets) use ($depreciation, $dryRun, &$created) {
                foreach ($assets as $asset) {
                    if ($asset->activeDepreciationSetting()) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  [dry-run] asset #{$asset->id} \"{$asset->name}\" -> schedule from category default");
                        $created++;
                        continue;
                    }

                    if ($depreciation->applyCategoryDefault($asset)) {
                        $this->line("  asset #{$asset->id} \"{$asset->name}\": depreciation created");
                        $created++;
                    }
                }
            });

        if ($created === 0) {
            $this->info('No assets needed a depreciation schedule.');
        } else {
            $this->info(($dryRun ? "Would create {$created}" : "Created {$created}") . ' depreciation schedule(s).');
        }

        return self::SUCCESS;
    }
}
