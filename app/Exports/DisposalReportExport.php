<?php

namespace App\Exports;

use App\Models\DisposalRequest;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DisposalReportExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Asset Tag', 'Asset Name', 'Company', 'Disposal Type', 'Status', 'Reason',
        'Disposal Value', 'Requested By', 'Written Off By', 'Written Off At',
        'Scrapped By', 'Scrapped At',
    ];

    private Collection $disposals;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->disposals = $this->reports->buildDisposalQuery($this->filters)->get();
    }

    public function rowCount(): int
    {
        return $this->disposals->count();
    }

    public function collection(): Collection
    {
        return $this->disposals;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  DisposalRequest  $disposal */
    public function map($disposal): array
    {
        return [
            $disposal->asset?->asset_tag,
            $disposal->asset?->name,
            $disposal->asset?->company?->name,
            $disposal->disposalType?->name,
            ucwords(str_replace('_', ' ', $disposal->status)),
            $disposal->reason,
            $disposal->disposal_value,
            $disposal->requestedBy?->name,
            $disposal->writtenOffBy?->name,
            optional($disposal->written_off_at)->format('Y-m-d H:i'),
            $disposal->scrappedBy?->name,
            optional($disposal->scrapped_at)->format('Y-m-d H:i'),
        ];
    }
}
