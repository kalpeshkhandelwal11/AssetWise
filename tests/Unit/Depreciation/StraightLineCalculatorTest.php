<?php

namespace Tests\Unit\Depreciation;

use App\Services\Depreciation\DepreciationInput;
use App\Services\Depreciation\StraightLineCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class StraightLineCalculatorTest extends TestCase
{
    private function sum(array $lines): float
    {
        return round(array_sum(array_map(fn ($l) => $l->depreciationAmount, $lines)), 2);
    }

    public function test_full_month_schedule_sums_to_depreciable_and_closes_at_salvage(): void
    {
        $calc = new StraightLineCalculator();
        $lines = $calc->generate(new DepreciationInput(100000, 5000, 60, Carbon::parse('2024-01-01')));

        $this->assertCount(60, $lines);
        $this->assertEqualsWithDelta(95000, $this->sum($lines), 0.01);
        $this->assertEqualsWithDelta(5000, end($lines)->closingBookValue, 0.01);
        $this->assertSame(2024, $lines[0]->periodYear);
        $this->assertSame(1, $lines[0]->periodMonth);
        $this->assertSame(31, $lines[0]->daysInPeriod);
    }

    public function test_partial_first_and_last_month_are_prorated_by_days(): void
    {
        $calc = new StraightLineCalculator();
        // 2024-01-20 + 12 months -> 2025-01-20 : partial Jan 2024, 11 full months, partial Jan 2025.
        $lines = $calc->generate(new DepreciationInput(12000, 0, 12, Carbon::parse('2024-01-20')));

        $this->assertCount(13, $lines);
        $this->assertEqualsWithDelta(12000, $this->sum($lines), 0.01);
        $this->assertSame(12, $lines[0]->daysInPeriod);       // Jan 20 -> Feb 1
        $this->assertSame(19, end($lines)->daysInPeriod);     // Jan 1 -> Jan 20
        $this->assertEqualsWithDelta(0, end($lines)->closingBookValue, 0.01);
    }

    public function test_no_depreciable_amount_yields_no_lines(): void
    {
        $calc = new StraightLineCalculator();

        $this->assertSame([], $calc->generate(new DepreciationInput(5000, 5000, 12, Carbon::parse('2024-01-01'))));
        $this->assertSame([], $calc->generate(new DepreciationInput(10000, 0, 0, Carbon::parse('2024-01-01'))));
    }

    public function test_accumulated_depreciation_is_monotonic(): void
    {
        $calc = new StraightLineCalculator();
        $lines = $calc->generate(new DepreciationInput(50000, 2500, 36, Carbon::parse('2024-03-15')));

        $previous = 0.0;
        foreach ($lines as $line) {
            $this->assertGreaterThanOrEqual($previous, $line->accumulatedDepreciation);
            $previous = $line->accumulatedDepreciation;
        }
        $this->assertEqualsWithDelta(47500, $previous, 0.01);
    }
}
