<?php

namespace App\Services\Depreciation;

/**
 * One computed period of a depreciation schedule, before it is persisted as a
 * depreciation_schedule_lines row by DepreciationService.
 */
final class ScheduleLineDTO
{
    public function __construct(
        public readonly int $periodYear,
        public readonly int $periodMonth,
        public readonly int $daysInPeriod,
        public readonly float $openingBookValue,
        public readonly float $depreciationAmount,
        public readonly float $accumulatedDepreciation,
        public readonly float $closingBookValue,
    ) {
    }
}
