<?php

namespace App\Exports;

use App\Models\Asset;
use App\Services\DynamicFieldService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Per-category upload template. Headers are core columns + company_code + one column
 * per field resolved by DynamicFieldService::resolveForCategory() — the single source
 * of truth also used by AssetImport, so the template always matches what import expects.
 *
 * To show users the exact shape of valid data, the template also carries one sample row
 * populated from a real existing asset in the category (its `name` is prefixed so it's
 * obviously an example to delete before uploading). If the category has no assets yet,
 * only the header is emitted.
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
        $asset = Asset::with(['company', 'fieldValues.categoryField'])
            ->where('category_id', $this->categoryId)
            ->latest('id')
            ->first();

        if (! $asset) {
            return [];
        }

        $values = $this->rowFrom($asset);

        // Emit the values in the exact heading order; any column without a value is blank.
        return [array_map(fn ($heading) => $values[$heading] ?? '', $this->headings())];
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

    /** Map a real asset to the template columns (import resolves company by code, not id). */
    private function rowFrom(Asset $asset): array
    {
        $fieldValues = [];
        foreach ($asset->fieldValues as $fieldValue) {
            if ($fieldValue->categoryField) {
                $fieldValues[$fieldValue->categoryField->field_key] = $this->scalar($fieldValue);
            }
        }

        return array_merge([
            'name'            => 'SAMPLE — delete this row: ' . $asset->name,
            'description'     => $asset->description,
            'serial_number'   => $asset->serial_number,
            'model'           => $asset->model,
            'manufacturer'    => $asset->manufacturer,
            'company_code'    => $asset->company?->code,
            'asset_type_id'   => $asset->asset_type_id,
            'status_id'       => $asset->status_id,
            'location_id'     => $asset->location_id,
            'custodian_id'    => $asset->custodian_id,
            'department_id'   => $asset->department_id,
            'branch_id'       => $asset->branch_id,
            'purchase_date'   => $this->date($asset->purchase_date),
            'purchase_cost'   => $asset->purchase_cost,
            'vendor'          => $asset->vendor,
            'warranty_expiry' => $this->date($asset->warranty_expiry),
            'amc_expiry'      => $this->date($asset->amc_expiry),
            'notes'           => $asset->notes,
        ], $fieldValues);
    }

    /** Pull the stored EAV value regardless of which typed column holds it. */
    private function scalar(object $fieldValue): mixed
    {
        return match (true) {
            ! is_null($fieldValue->value_text)    => $fieldValue->value_text,
            ! is_null($fieldValue->value_number)  => $fieldValue->value_number,
            ! is_null($fieldValue->value_date)    => $this->date($fieldValue->value_date),
            ! is_null($fieldValue->value_boolean) => (int) $fieldValue->value_boolean,
            default                                => '',
        };
    }

    private function date(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : '';
    }
}
