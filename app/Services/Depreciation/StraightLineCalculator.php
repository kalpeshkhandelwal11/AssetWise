<?php

namespace App\Services\Depreciation;

/**
 * Straight-line depreciation, Companies Act 2013 Schedule II style: the depreciable amount
 * (cost - salvage) is spread evenly across the useful life on a per-day basis, so the first
 * and last calendar months are partial. Rounding residue is absorbed into the final line so
 * the schedule sums exactly to the depreciable amount and closes at the salvage value.
 */
class StraightLineCalculator implements DepreciationCalculatorInterface
{
    public function generate(DepreciationInput $input): array
    {
        $depreciable = $input->depreciableAmount();

        if ($depreciable <= 0 || $input->usefulLifeMonths <= 0) {
            return [];
        }

        $start = $input->startDate->copy()->startOfDay();
        $end   = $start->copy()->addMonths($input->usefulLifeMonths); // exclusive
        $totalDays = (int) $start->diffInDays($end);

        if ($totalDays <= 0) {
            return [];
        }

        $dailyRate = $depreciable / $totalDays;

        // Pass 1 — split the life into calendar-month segments with day counts + raw amounts.
        $segments = [];
        $cursor = $start->copy();

        while ($cursor->lessThan($end)) {
            $nextMonthStart = $cursor->copy()->startOfMonth()->addMonth();
            $segmentEnd = $nextMonthStart->lessThan($end) ? $nextMonthStart : $end->copy();
            $days = (int) $cursor->diffInDays($segmentEnd);

            $segments[] = [
                'year'   => $cursor->year,
                'month'  => $cursor->month,
                'days'   => $days,
                'amount' => round($dailyRate * $days, 2),
            ];

            $cursor = $segmentEnd;
        }

        // Force the amounts to sum exactly to the depreciable base via the final line.
        $sumExceptLast = 0.0;
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $sumExceptLast += $segments[$i]['amount'];
        }
        $segments[count($segments) - 1]['amount'] = round($depreciable - $sumExceptLast, 2);

        // Pass 2 — run opening/closing book value + accumulated depreciation forward.
        $lines = [];
        $opening = $input->costBasis;
        $accumulated = 0.0;

        foreach ($segments as $segment) {
            $accumulated = round($accumulated + $segment['amount'], 2);
            $closing = round($input->costBasis - $accumulated, 2);

            $lines[] = new ScheduleLineDTO(
                periodYear: $segment['year'],
                periodMonth: $segment['month'],
                daysInPeriod: $segment['days'],
                openingBookValue: $opening,
                depreciationAmount: $segment['amount'],
                accumulatedDepreciation: $accumulated,
                closingBookValue: $closing,
            );

            $opening = $closing;
        }

        return $lines;
    }
}
