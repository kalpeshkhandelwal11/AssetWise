<?php

namespace App\Exports\Sheets;

use App\Imports\Support\MasterLookup;
use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Services\DynamicFieldService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

/**
 * The data sheet of AssetTemplateExport. Master columns hold names (custodian as
 * "Name (EMP001)"), resolved back to ids by AssetImport via MasterLookup.
 *
 * Carries one sample row from a real asset of the category (name prefixed "SAMPLE — delete
 * this row", which AssetImport skips) so people see the exact shape of valid data.
 */
class AssetTemplateDataSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    /** Rows that get dropdown validation (header is row 1). */
    private const VALIDATED_ROWS = 1000;

    public function __construct(
        private readonly int $categoryId,
        private readonly DynamicFieldService $fields,
    ) {
    }

    public function title(): string
    {
        return 'Assets';
    }

    public function array(): array
    {
        $asset = Asset::with(['company', 'assetType', 'status', 'location', 'custodian', 'department', 'branch', 'fieldValues.categoryField'])
            ->where('category_id', $this->categoryId)
            ->latest('id')
            ->first();

        if (! $asset) {
            return [];
        }

        $values = $this->rowFrom($asset);

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
            'asset_type', 'status', 'location', 'custodian', 'department', 'branch',
            'purchase_date', 'purchase_cost', 'vendor', 'vendor_invoice_no', 'warranty_expiry', 'amc_expiry', 'notes',
        ];
    }

    /**
     * Dropdown source lists, keyed by heading. Custodian is deliberately excluded — staff
     * lists are too long for a usable dropdown; the header comment explains the format.
     *
     * @return array<string, string[]>
     */
    public function listColumns(): array
    {
        $names = fn (string $model) => $model::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();

        $lists = [
            'company_code' => Company::visible()->where('is_active', true)->orderBy('code')->pluck('code')->all(),
            'asset_type'   => $names(AssetType::class),
            'status'       => AssetStatus::where('is_active', true)
                ->whereNotIn('code', ['DRAFT', 'IN_TRANSIT', 'DISPOSED', 'RETURNED', 'HANDED_OVER', 'SOLD_EXT', 'ICT'])
                ->orderBy('name')->pluck('name')->all(),
            'location'     => $names(Location::class),
            'department'   => $names(Department::class),
            'branch'       => $names(Branch::class),
        ];

        foreach ($this->fields->resolveForCategory($this->categoryId) as $field) {
            if ($field->fieldType === 'dropdown') {
                $lists[$field->fieldKey] = collect($field->options)->pluck('label')->all();
            } elseif ($field->fieldType === 'boolean') {
                $lists[$field->fieldKey] = ['Yes', 'No'];
            }
        }

        return array_filter($lists);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $headings = $this->headings();
                $listColumn = 1;

                foreach ($this->listColumns() as $heading => $values) {
                    $index = array_search($heading, $headings, true);

                    if ($index === false) {
                        $listColumn++;

                        continue;
                    }

                    $letter = Coordinate::stringFromColumnIndex($index + 1);
                    $listLetter = Coordinate::stringFromColumnIndex($listColumn);
                    $formula = sprintf("'%s'!\$%s\$2:\$%s\$%d", TemplateListsSheet::TITLE, $listLetter, $listLetter, count($values) + 1);

                    $validation = new DataValidation;
                    $validation->setType(DataValidation::TYPE_LIST)
                        ->setErrorStyle(DataValidation::STYLE_STOP)
                        ->setAllowBlank(true)
                        ->setShowDropDown(true)
                        ->setShowErrorMessage(true)
                        ->setErrorTitle('Pick from the list')
                        ->setError('Choose a value from the dropdown.')
                        ->setFormula1($formula);

                    $sheet->setDataValidation("{$letter}2:{$letter}".(self::VALIDATED_ROWS + 1), $validation);
                    $listColumn++;
                }

                $custodian = array_search('custodian', $headings, true);
                if ($custodian !== false) {
                    $cell = Coordinate::stringFromColumnIndex($custodian + 1).'1';
                    $sheet->getComment($cell)->getText()->createText('Employee code (EMP001), or "Name (EMP001)". A bare name works only if it is unique.');
                }
            },
        ];
    }

    private function rowFrom(Asset $asset): array
    {
        $fieldValues = [];
        foreach ($asset->fieldValues as $fieldValue) {
            if ($fieldValue->categoryField) {
                $fieldValues[$fieldValue->categoryField->field_key] = $this->scalar($fieldValue);
            }
        }

        return array_merge([
            'name'              => 'SAMPLE — delete this row: ' . $asset->name,
            'description'       => $asset->description,
            'serial_number'     => '', // serials are unique — never pre-fill a real one
            'model'             => $asset->model,
            'manufacturer'      => $asset->manufacturer,
            'company_code'      => $asset->company?->code,
            'asset_type'        => $asset->assetType?->name,
            'status'            => $asset->status?->name,
            'location'          => $asset->location?->name,
            'custodian'         => MasterLookup::displayEmployee($asset->custodian),
            'department'        => $asset->department?->name,
            'branch'            => $asset->branch?->name,
            'purchase_date'     => $this->date($asset->purchase_date),
            'purchase_cost'     => $asset->purchase_cost,
            'vendor'            => $asset->vendor,
            'vendor_invoice_no' => $asset->vendor_invoice_no,
            'warranty_expiry'   => $this->date($asset->warranty_expiry),
            'amc_expiry'        => $this->date($asset->amc_expiry),
            'notes'             => $asset->notes,
        ], $fieldValues);
    }

    private function scalar(object $fieldValue): mixed
    {
        return match (true) {
            ! is_null($fieldValue->value_text)    => $fieldValue->value_text,
            ! is_null($fieldValue->value_number)  => $fieldValue->value_number,
            ! is_null($fieldValue->value_date)    => $this->date($fieldValue->value_date),
            ! is_null($fieldValue->value_boolean) => $fieldValue->value_boolean ? 'Yes' : 'No',
            default                                => '',
        };
    }

    private function date(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : '';
    }
}
