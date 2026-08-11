<?php

namespace App\Console\Commands;

use App\Services\DepreciationService;
use Illuminate\Console\Command;

class PostMonthlyDepreciation extends Command
{
    protected $signature = 'depreciation:post-monthly';

    protected $description = 'Post every due depreciation schedule line for active assets and refresh cached book values (idempotent)';

    public function handle(DepreciationService $depreciation): int
    {
        $count = $depreciation->postDuePeriods();

        $this->info($count === 0
            ? 'No depreciation lines were due for posting.'
            : "Posted {$count} depreciation line(s).");

        return self::SUCCESS;
    }
}
