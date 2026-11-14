<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Serial numbers are unique app-wide (App\Rules\UniqueSerialNumber). Legacy data may still
 * hold duplicates or placeholder serials ("NA", "-"). This lists them; with --clean-placeholders
 * it blanks the placeholders, and with --enforce it adds a real DB unique index — only once
 * no duplicates remain (multiple NULLs are allowed by both MySQL and SQLite).
 */
class SerialDuplicates extends Command
{
    protected $signature = 'assets:serial-duplicates
        {--clean-placeholders : Blank out NA / N/A / - style placeholder serials}
        {--enforce : Add a DB unique index on assets.serial_number when the data is clean}';

    protected $description = 'Report duplicate serial numbers and optionally enforce uniqueness in the database';

    public function handle(): int
    {
        if ($this->option('clean-placeholders')) {
            $count = Asset::withoutGlobalScopes()->withTrashed()
                ->where(fn ($q) => $q->whereIn(DB::raw('LOWER(TRIM(serial_number))'), \App\Support\SerialNumber::PLACEHOLDERS)
                    ->orWhereRaw("TRIM(serial_number) = ''"))
                ->update(['serial_number' => null]);
            $this->info("Blanked {$count} placeholder serial(s).");
        }

        $groups = Asset::withoutGlobalScopes()->whereNull('deleted_at')->whereNotNull('serial_number')
            ->selectRaw('LOWER(TRIM(serial_number)) as serial, count(*) as total')
            ->groupByRaw('LOWER(TRIM(serial_number))')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('total')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No duplicate serial numbers.');
        } else {
            $this->warn("{$groups->count()} duplicated serial number(s):");
            foreach ($groups as $group) {
                $tags = Asset::withoutGlobalScopes()->whereNull('deleted_at')
                    ->whereRaw('LOWER(TRIM(serial_number)) = ?', [$group->serial])
                    ->pluck('asset_tag')->implode(', ');
                $this->line("  {$group->serial} ×{$group->total}: {$tags}");
            }
        }

        if ($this->option('enforce')) {
            if ($groups->isNotEmpty()) {
                $this->error('Not enforcing: resolve the duplicates above first.');

                return self::FAILURE;
            }

            if (! collect(Schema::getIndexes('assets'))->contains(fn ($i) => $i['name'] === 'assets_serial_number_unique')) {
                Schema::table('assets', fn ($table) => $table->unique('serial_number'));
            }

            $this->info('Unique index on assets.serial_number is in place.');
        }

        return $groups->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
