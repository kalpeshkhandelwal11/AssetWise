<?php

namespace Database\Seeders;

use App\Models\DepreciationMethod;
use App\Services\Depreciation\DecliningBalanceCalculator;
use App\Services\Depreciation\DoubleDecliningBalanceCalculator;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\Depreciation\SumOfYearsDigitsCalculator;
use App\Services\Depreciation\UnitsOfProductionCalculator;
use Illuminate\Database\Seeder;

/**
 * Depreciation methods (M16). Only straight_line is active in the MVP; the rest are seeded
 * so the strategy binding is complete and admins can see them, but they're not selectable
 * until activated (which needs no migration — the "add methods without schema change" goal).
 */
class DepreciationMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'straight_line',            'name' => 'Straight Line',            'calculator_class' => StraightLineCalculator::class,           'is_active' => true],
            ['code' => 'declining_balance',        'name' => 'Declining Balance',        'calculator_class' => DecliningBalanceCalculator::class,       'is_active' => false],
            ['code' => 'double_declining_balance', 'name' => 'Double Declining Balance', 'calculator_class' => DoubleDecliningBalanceCalculator::class, 'is_active' => false],
            ['code' => 'sum_of_years_digits',      'name' => 'Sum of Years Digits',      'calculator_class' => SumOfYearsDigitsCalculator::class,       'is_active' => false],
            ['code' => 'units_of_production',      'name' => 'Units of Production',      'calculator_class' => UnitsOfProductionCalculator::class,      'is_active' => false],
        ];

        foreach ($methods as $method) {
            DepreciationMethod::firstOrCreate(
                ['code' => $method['code']],
                $method,
            );
        }
    }
}
