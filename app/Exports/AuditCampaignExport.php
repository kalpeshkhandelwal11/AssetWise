<?php

namespace App\Exports;

use App\Models\AuditItem;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuditCampaignExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Campaign', 'Audit Type', 'Asset Tag', 'Asset Name', 'Company',
        'Expected Location', 'Expected Custodian', 'Status', 'Verified By', 'Verified At', 'Notes',
    ];

    private Collection $items;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->items = $this->reports->buildAuditCampaignQuery($this->filters)->get();
    }

    public function rowCount(): int
    {
        return $this->items->count();
    }

    public function collection(): Collection
    {
        return $this->items;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  AuditItem  $item */
    public function map($item): array
    {
        return [
            $item->campaign?->name,
            $item->campaign?->auditType?->name,
            $item->asset?->asset_tag,
            $item->asset?->name,
            $item->asset?->company?->name,
            $item->expectedLocation?->name,
            $item->expectedCustodian?->name,
            ucfirst($item->status),
            $item->verifiedBy?->name,
            optional($item->verified_at)->format('Y-m-d H:i'),
            $item->notes,
        ];
    }
}
