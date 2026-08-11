<?php

namespace App\Exports;

use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UtilizationReportExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Company', 'Total', 'Assigned', 'Available', 'In Maintenance', 'Utilization %',
    ];

    private Collection $rows;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->rows = $this->reports->buildUtilizationRows($this->filters);
    }

    public function rowCount(): int
    {
        return $this->rows->count();
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  array<string, mixed>  $row */
    public function map($row): array
    {
        return [
            $row['company']->name,
            $row['total'],
            $row['assigned'],
            $row['available'],
            $row['in_maintenance'],
            $row['utilization_pct'],
        ];
    }
}
