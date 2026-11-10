<?php

namespace App\Exports;

use App\Services\Reports\DepreciationRegisterBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Excel + PDF (via ReportPdfExporter) for the FY Depreciation Register. */
class DepreciationRegisterExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    private const HEADINGS = [
        'SI No.', 'Asset Tag', 'Asset Description', 'Schedule II Class', 'Date of Purchase',
        'Date From', 'Date To', 'Cost of Asset', 'Residual Value', 'Useful Life (Yrs)', 'Method',
        'Dep Rate (%)', 'Days Used', 'Opening WDV', 'Depreciation', 'Closing WDV',
    ];

    private Collection $rows;

    public function __construct(
        private readonly array $filters,
        private readonly DepreciationRegisterBuilder $builder,
    ) {
        $this->rows = $builder->rows($filters);
    }

    public function title(): string
    {
        return 'Depreciation Register';
    }

    /** Asset rows only (subtotals/total are presentation). */
    public function rowCount(): int
    {
        return $this->rows->where('kind', 'asset')->count();
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param array<string, mixed> $row */
    public function map($row): array
    {
        if ($row['kind'] !== 'asset') {
            return ['', '', $row['description'], '', '', '', '', $row['cost'], $row['residual'], '', '', '', '',
                $row['opening_wdv'], $row['depreciation'], $row['closing_wdv']];
        }

        return [
            $row['si'],
            $row['asset_tag'],
            ($row['is_addition'] ? 'Add: Purchase — ' : '').$row['description'],
            $row['class'],
            $row['purchase_date'],
            $row['date_from'],
            $row['date_to'],
            $row['cost'],
            $row['residual'],
            $row['life_years'],
            $row['method'],
            $row['rate'],
            $row['days'],
            $row['opening_wdv'],
            $row['depreciation'],
            $row['closing_wdv'],
        ];
    }
}
