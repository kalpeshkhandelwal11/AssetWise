<?php

namespace App\Exports;

use App\Services\DynamicFieldService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * PDF flavour of AssetExport: the same filtered rows, but a fixed set of core columns — the
 * full export (30+ columns plus every custom field) cannot fit an A4 landscape page.
 * Rendered by ReportPdfExporter, exactly like the M14 reports.
 */
class AssetPdfExport implements FromCollection, WithHeadings, WithMapping
{
    public const COLUMNS = [
        'Asset Tag', 'Name', 'Serial Number', 'Category', 'Company Name', 'Status',
        'Location', 'Custodian', 'Purchase Date', 'Purchase Cost',
    ];

    /** Above this, DomPDF on shared hosting runs out of memory — use Excel instead. */
    public const MAX_ROWS = 3000;

    private AssetExport $inner;

    /** @var int[] positions of COLUMNS within the full export's headings */
    private array $indexes;

    public function __construct(array $filters, DynamicFieldService $fields)
    {
        $this->inner = new AssetExport($filters, $fields);
        $headings = $this->inner->headings();
        $this->indexes = array_map(fn ($h) => array_search($h, $headings, true), self::COLUMNS);
    }

    public function rowCount(): int
    {
        return $this->inner->rowCount();
    }

    public function collection(): Collection
    {
        return $this->inner->collection();
    }

    public function headings(): array
    {
        return self::COLUMNS;
    }

    public function map($asset): array
    {
        $row = $this->inner->map($asset);

        return array_map(fn ($i) => $i === false ? '' : ($row[$i] ?? ''), $this->indexes);
    }
}
