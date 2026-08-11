<?php

namespace App\Services\Depreciation;

use Carbon\CarbonInterface;

/**
 * Immutable input to a depreciation calculator. Salvage is already resolved to an absolute
 * amount by DepreciationService before it reaches the calculator, so calculators never deal
 * with the fixed-vs-percent distinction.
 */
final class DepreciationInput
{
    public function __construct(
        public readonly float $costBasis,
        public readonly float $salvageValue,
        public readonly int $usefulLifeMonths,
        public readonly CarbonInterface $startDate,
    ) {
    }

    public function depreciableAmount(): float
    {
        return round(max(0.0, $this->costBasis - $this->salvageValue), 2);
    }
}
