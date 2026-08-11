<?php

namespace App\Exports;

use App\Models\Asset;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetAgingExport implements FromCollection, WithHeadings, WithMapping
{
    private const HEADINGS = [
        'Asset Tag', 'Asset Name', 'Company', 'Category', 'Purchase Date', 'Age (Years)', 'Age Bucket',
    ];

    private Collection $assets;

    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $reports,
    ) {
        $this->assets = $this->reports->buildAgingQuery($this->filters)->get();
    }

    public function rowCount(): int
    {
        return $this->assets->count();
    }

    public function collection(): Collection
    {
        return $this->assets;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /** @param  Asset  $asset */
    public function map($asset): array
    {
        return [
            $asset->asset_tag,
            $asset->name,
            $asset->company?->name,
            $asset->category?->name,
            optional($asset->purchase_date)->format('Y-m-d'),
            $asset->purchase_date?->diffInYears(now()),
            ReportService::ageBucket($asset->purchase_date),
        ];
    }
}
