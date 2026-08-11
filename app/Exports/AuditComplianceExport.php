<?php

namespace App\Exports;

use App\Models\AssetStatusHistory;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuditComplianceExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Asset Tag', 'Asset Name', 'Company', 'From Status', 'To Status', 'Changed By', 'Reason', 'Changed At',
    ];

    private Collection $histories;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->histories = $this->reports->buildAuditComplianceQuery($this->filters)->get();
    }

    public function rowCount(): int
    {
        return $this->histories->count();
    }

    public function collection(): Collection
    {
        return $this->histories;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  AssetStatusHistory  $history */
    public function map($history): array
    {
        return [
            $history->asset?->asset_tag,
            $history->asset?->name,
            $history->asset?->company?->name,
            $history->fromStatus?->name,
            $history->toStatus?->name,
            $history->changedBy?->name,
            $history->reason,
            optional($history->created_at)->format('Y-m-d H:i'),
        ];
    }
}
