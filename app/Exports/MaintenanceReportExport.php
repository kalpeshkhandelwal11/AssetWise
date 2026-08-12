<?php

namespace App\Exports;

use App\Models\MaintenanceRecord;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MaintenanceReportExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Asset Tag', 'Asset Name', 'Company', 'Type', 'Status', 'Scheduled', 'Performed',
        'Vendor', 'Cost', 'Capitalized', 'Capitalized Amount', 'Extra Life (months)',
        'Logged By', 'Description',
    ];

    private Collection $records;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->records = $this->reports->buildMaintenanceQuery($this->filters)->get();
    }

    public function rowCount(): int
    {
        return $this->records->count();
    }

    public function collection(): Collection
    {
        return $this->records;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  MaintenanceRecord  $record */
    public function map($record): array
    {
        return [
            $record->asset?->asset_tag,
            $record->asset?->name,
            $record->asset?->company?->name,
            $record->maintenanceType?->name,
            ucwords(str_replace('_', ' ', $record->status)),
            optional($record->scheduled_date)->format('Y-m-d'),
            optional($record->performed_date)->format('Y-m-d'),
            $record->vendor,
            $record->cost,
            $record->is_capitalized ? 'Yes' : 'No',
            $record->capitalized_amount,
            $record->additional_useful_life_months,
            $record->loggedBy?->name,
            $record->description,
        ];
    }
}
