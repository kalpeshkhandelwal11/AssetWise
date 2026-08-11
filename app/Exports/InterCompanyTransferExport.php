<?php

namespace App\Exports;

use App\Models\AssetMovement;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InterCompanyTransferExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Asset Tag', 'Asset Name', 'Category', 'From Company', 'To Company',
        'Requested By', 'Approver', 'Requested Date', 'Completed Date', 'Status',
    ];

    private Collection $transfers;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->transfers = $this->reports->buildInterCompanyTransferQuery($this->filters)->get();
    }

    public function rowCount(): int
    {
        return $this->transfers->count();
    }

    public function collection(): Collection
    {
        return $this->transfers;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  AssetMovement  $movement */
    public function map($movement): array
    {
        $approver = $movement->approvalRequest?->actions
            ?->firstWhere('action', 'approve')?->user?->name;

        return [
            $movement->asset?->asset_tag,
            $movement->asset?->name,
            $movement->asset?->category?->name,
            $movement->fromCompany?->name,
            $movement->toCompany?->name,
            $movement->requestedBy?->name,
            $approver,
            optional($movement->created_at)->format('Y-m-d'),
            optional($movement->verified_at)->format('Y-m-d'),
            ucfirst(str_replace('_', ' ', $movement->status)),
        ];
    }
}
