<?php

namespace App\Exports;

use App\Models\AssetMovement;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MovementReportExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Asset Tag', 'Asset Name', 'Movement Type', 'From Company', 'To Company',
        'From Location', 'To Location', 'From Custodian', 'To Custodian',
        'Status', 'Requested By', 'Requested At', 'Verified By', 'Verified At',
    ];

    private Collection $movements;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->movements = $this->reports->buildMovementQuery($this->filters)->get();
    }

    public function rowCount(): int
    {
        return $this->movements->count();
    }

    public function collection(): Collection
    {
        return $this->movements;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  AssetMovement  $movement */
    public function map($movement): array
    {
        return [
            $movement->asset?->asset_tag,
            $movement->asset?->name,
            $movement->movementType?->name,
            $movement->fromCompany?->name,
            $movement->toCompany?->name,
            $movement->fromLocation?->name,
            $movement->toLocation?->name,
            $movement->fromCustodian?->name,
            $movement->toCustodian?->name,
            ucfirst(str_replace('_', ' ', $movement->status)),
            $movement->requestedBy?->name,
            optional($movement->created_at)->format('Y-m-d H:i'),
            $movement->verifiedBy?->name,
            optional($movement->verified_at)->format('Y-m-d H:i'),
        ];
    }
}
