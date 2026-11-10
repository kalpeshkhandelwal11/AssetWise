<?php

namespace App\Exports;

use App\Exports\Sheets\AssetTemplateDataSheet;
use App\Exports\Sheets\TemplateListsSheet;
use App\Services\DynamicFieldService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Per-category upload template: a data sheet (headings + an optional sample row) plus a
 * hidden "Lists" sheet that feeds Excel dropdowns for every master column, so people pick
 * names instead of typing — and never see a database id. AssetImport reads only sheet 0.
 */
class AssetTemplateExport implements WithMultipleSheets
{
    public function __construct(
        private readonly int $categoryId,
        private readonly DynamicFieldService $fields,
    ) {
    }

    public function sheets(): array
    {
        $data = new AssetTemplateDataSheet($this->categoryId, $this->fields);

        return [$data, new TemplateListsSheet($data->listColumns())];
    }

    /** Kept for callers/tests that inspect the data sheet's columns directly. */
    public function headings(): array
    {
        return (new AssetTemplateDataSheet($this->categoryId, $this->fields))->headings();
    }

    public function array(): array
    {
        return (new AssetTemplateDataSheet($this->categoryId, $this->fields))->array();
    }
}
