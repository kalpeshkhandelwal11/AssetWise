<?php

namespace App\Services\Depreciation;

/**
 * Strategy contract (M16). New depreciation methods are added by implementing this and
 * flipping their depreciation_methods.is_active flag — no schema or service changes.
 */
interface DepreciationCalculatorInterface
{
    /**
     * Build the full period-by-period schedule for the given input.
     *
     * @return ScheduleLineDTO[] ordered by period; amounts must sum exactly to the
     *                           depreciable amount (cost - salvage).
     */
    public function generate(DepreciationInput $input): array;
}
