<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\AssetNamingService;
use Illuminate\Console\Command;

/**
 * One-off cleanup for the "asset_tag clobbered by tag assignment" bug: before the fix,
 * TagService overwrote assets.asset_tag (the generated Asset ID) with the assigned pool tag's
 * number. This regenerates a proper Asset ID for every asset whose asset_tag still equals the
 * number of a tag assigned to it. The barcode link (asset_tag_assignments) is left untouched.
 */
class BackfillClobberedAssetIds extends Command
{
    protected $signature = 'assets:backfill-clobbered-ids {--dry-run : List what would change without writing}';

    protected $description = 'Regenerate Asset IDs that were overwritten with a pool tag number';

    public function handle(AssetNamingService $naming): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;

        $assets = Asset::withTrashed()
            ->whereHas('tagAssignments')
            ->with(['tagAssignments.tag', 'category'])
            ->orderBy('id')
            ->get();

        foreach ($assets as $asset) {
            $assignedNumbers = $asset->tagAssignments
                ->pluck('tag.tag_number')
                ->filter()
                ->all();

            // Clobbered iff the Asset ID is literally one of this asset's tag numbers.
            if (! in_array($asset->asset_tag, $assignedNumbers, true)) {
                continue;
            }

            $old = $asset->asset_tag;

            if ($dryRun) {
                // Don't call next() here — it would consume counter numbers on a preview run.
                $this->line("  [dry-run] asset #{$asset->id} \"{$asset->name}\": {$old} -> (new Asset ID)");
            } else {
                $new = $naming->next($asset->category);
                $asset->update(['asset_tag' => $new]);
                $this->line("  asset #{$asset->id} \"{$asset->name}\": {$old} -> {$new}");
            }
            $fixed++;
        }

        if ($fixed === 0) {
            $this->info('No clobbered Asset IDs found.');
        } else {
            $this->info(($dryRun ? "Would regenerate {$fixed}" : "Regenerated {$fixed}") . ' Asset ID(s).');
        }

        return self::SUCCESS;
    }
}
