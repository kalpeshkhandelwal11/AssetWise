<?php

namespace App\Exports;

use App\Services\DynamicFieldService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Per-category upload template. Headers are core columns + company_code + one column
 * per field resolved by DynamicFieldService::resolveForCategory() — the single source
 * of truth also used by AssetImport, so the template always matches what import expects.
 */
class AssetTemplateExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly int $categoryId,
        private readonly DynamicFieldService $fields,
    ) {
    }

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return array_merge(
            $this->coreColumns(),
            $this->fields->resolveForCategory($this->categoryId)->pluck('fieldKey')->all(),
        );
    }

    private function coreColumns(): array
    {
        return [
            'name', 'description', 'serial_number', 'model', 'manufacturer', 'company_code',
            'asset_type_id', 'status_id', 'location_id', 'custodian_id', 'department_id', 'branch_id',
            'purchase_date', 'purchase_cost', 'vendor', 'warranty_expiry', 'amc_expiry', 'notes',
        ];
    }
}
