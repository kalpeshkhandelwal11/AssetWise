<?php

namespace App\Exports;

use App\Models\DepreciationScheduleLine;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DepreciationReportExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Asset Tag', 'Asset Name', 'Company', 'Category', 'Department', 'Method',
        'Period', 'Days', 'Depreciation', 'Accumulated', 'Book Value', 'Status',
    ];

    private Collection $lines;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->lines = $this->reports->buildDepreciationScheduleQuery($this->filters)->get();
    }

    public function rowCount(): int
    {
        return $this->lines->count();
    }

    public function collection(): Collection
    {
        return $this->lines;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  DepreciationScheduleLine  $line */
    public function map($line): array
    {
        return [
            $line->asset?->asset_tag,
            $line->asset?->name,
            $line->asset?->company?->name,
            $line->asset?->category?->name,
            $line->asset?->department?->name,
            $line->setting?->method?->name,
            sprintf('%04d-%02d', $line->period_year, $line->period_month),
            $line->days_in_period,
            $line->depreciation_amount,
            $line->accumulated_depreciation,
            $line->closing_book_value,
            ucfirst($line->status),
        ];
    }
}
