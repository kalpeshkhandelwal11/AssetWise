<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;

/**
 * End of Life is derived, not ticked: an asset is EOL once its projected EOL date has arrived
 * (Asset::isPastEol()). Saves keep `is_eol` in step; this daily run (and every deploy) moves the
 * assets whose date arrived since, and clears flags that no date supports — the old manual
 * checkbox could be ticked with no date, or ahead of it. Idempotent.
 */
class SyncEndOfLife extends Command
{
    protected $signature = 'assets:sync-eol {--dry-run : Show what would change without writing}';

    protected $description = 'Set End of Life from the EOL projected date (date arrived = EOL)';

    public function handle(): int
    {
        // System-level: every company's assets, deleted ones included (they can be restored).
        $base = fn () => Asset::withoutGlobalScopes()->withTrashed();

        $reached = $base()->where('is_eol', false)->whereNotNull('eol_projected_date')
            ->whereDate('eol_projected_date', '<=', today());
        $notYet = $base()->where('is_eol', true)
            ->where(fn ($q) => $q->whereNull('eol_projected_date')->orWhereDate('eol_projected_date', '>', today()));

        $toSet = (clone $reached)->count();
        $toClear = (clone $notYet)->count();
        $noDate = $base()->where('is_eol', true)->whereNull('eol_projected_date')->count();

        if ($this->option('dry-run')) {
            $this->info("Would mark {$toSet} asset(s) End of Life and clear {$toClear} flag(s) ({$noDate} of them had no EOL date).");

            return self::SUCCESS;
        }

        // Query-level updates: a bulk flag sync, not an edit worth an activity-log entry each.
        $reached->update(['is_eol' => true]);
        $notYet->update(['is_eol' => false]);

        $this->info($toSet + $toClear === 0
            ? 'End of Life flags already match the EOL dates.'
            : "Marked {$toSet} asset(s) End of Life; cleared {$toClear} flag(s) ({$noDate} of them had no EOL date).");

        return self::SUCCESS;
    }
}
