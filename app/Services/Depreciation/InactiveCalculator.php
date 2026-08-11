<?php

namespace App\Services\Depreciation;

use RuntimeException;

/**
 * Base for the non-MVP methods seeded is_active=false. They exist so the strategy binding is
 * complete and the admin can see them, but DepreciationService never selects an inactive
 * method — if one is ever invoked it's a configuration bug, hence the hard failure.
 */
abstract class InactiveCalculator implements DepreciationCalculatorInterface
{
    public function generate(DepreciationInput $input): array
    {
        throw new RuntimeException(static::class . ' is not active in this MVP. Activate it in depreciation_methods first.');
    }
}
