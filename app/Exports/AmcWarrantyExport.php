<?php

namespace App\Exports;

use App\Models\Reports\CoverageContract;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AmcWarrantyExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Asset Tag', 'Asset Name', 'Company', 'Kind', 'Provider / Vendor', 'Start',
        'End', 'Days Remaining', 'Expiry Status', 'Cost', 'Coverage / Terms',
    ];

    private Collection $contracts;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->contracts = $this->reports->buildAmcWarrantyQuery($this->filters)->get();
    }

    public function rowCount(): int
    {
        return $this->contracts->count();
    }

    public function collection(): Collection
    {
        return $this->contracts;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  CoverageContract  $contract */
    public function map($contract): array
    {
        return [
            $contract->asset?->asset_tag,
            $contract->asset?->name,
            $contract->asset?->company?->name,
            ucfirst($contract->kind),
            $contract->provider_name,
            optional($contract->start_date)->format('Y-m-d'),
            optional($contract->end_date)->format('Y-m-d'),
            $contract->end_date ? now()->startOfDay()->diffInDays($contract->end_date->copy()->startOfDay(), false) : null,
            ucfirst(ReportService::expiryStatus($contract->end_date)),
            $contract->cost,
            $contract->terms_text,
        ];
    }
}
