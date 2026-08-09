<?php

namespace App\Exports;

use App\Models\Asset;
use App\Services\DynamicFieldService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Filtered asset export. Honors the exact same filter whitelist as AssetController::index()
 * so "export what I'm looking at" behaves as users expect. Custom-field columns are the
 * union of DynamicFieldService::resolveForCategory() across every category present in the
 * result set; a row only fills the columns owned by its own category, leaving the rest blank.
 */
class AssetExport implements FromCollection, WithHeadings, WithMapping
{
    private const CORE_HEADINGS = [
        'Asset Tag', 'Name', 'Description', 'Serial Number', 'Model', 'Manufacturer',
        'Company Code', 'Company Name', 'Category', 'Asset Type', 'Status', 'Location',
        'Custodian', 'Department', 'Branch', 'Purchase Date', 'Purchase Cost', 'Vendor',
        'Warranty Expiry', 'AMC Expiry', 'Notes',
    ];

    private Collection $assets;

    /** @var Collection<int, \App\Services\DynamicFields\ResolvedField> */
    private Collection $headerFields;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly array $filters,
        private readonly DynamicFieldService $fields,
    ) {
        $this->assets = $this->buildQuery()->get();
        $this->headerFields = $this->resolveHeaderFields();
    }

    public function buildQuery(): Builder
    {
        $query = Asset::query()->with([
            'company', 'category', 'assetType', 'status', 'location', 'custodian',
            'department', 'branch', 'fieldValues.categoryField',
        ]);

        if (! empty($this->filters['show_deleted'])) {
            $query->onlyTrashed();
        }

        if (! empty($this->filters['search'])) {
            $s = $this->filters['search'];
            $query->where(fn ($q) => $q
                ->where('asset_tag', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%")
                ->orWhere('serial_number', 'like', "%$s%"));
        }

        foreach (['company_id', 'category_id', 'asset_type_id', 'status_id', 'location_id', 'custodian_id', 'department_id', 'branch_id'] as $filter) {
            if (! empty($this->filters[$filter])) {
                $query->where($filter, $this->filters[$filter]);
            }
        }

        if (! empty($this->filters['date_from'])) {
            $query->whereDate('purchase_date', '>=', $this->filters['date_from']);
        }

        if (! empty($this->filters['date_to'])) {
            $query->whereDate('purchase_date', '<=', $this->filters['date_to']);
        }

        return $query->orderBy('name');
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
        return array_merge(self::CORE_HEADINGS, $this->headerFields->pluck('label')->all());
    }

    /**
     * @param  Asset  $asset
     */
    public function map($asset): array
    {
        $resolved = $this->fields->resolveForCategory($asset->category_id);
        $valuesByKey = $asset->fieldValues
            ->filter(fn ($fv) => $fv->categoryField !== null)
            ->keyBy(fn ($fv) => $fv->categoryField->field_key);

        $row = [
            $asset->asset_tag,
            $asset->name,
            $asset->description,
            $asset->serial_number,
            $asset->model,
            $asset->manufacturer,
            $asset->company?->code,
            $asset->company?->name,
            $asset->category?->name,
            $asset->assetType?->name,
            $asset->status?->name,
            $asset->location?->name,
            $asset->custodian?->name,
            $asset->department?->name,
            $asset->branch?->name,
            optional($asset->purchase_date)->format('Y-m-d'),
            $asset->purchase_cost,
            $asset->vendor,
            optional($asset->warranty_expiry)->format('Y-m-d'),
            optional($asset->amc_expiry)->format('Y-m-d'),
            $asset->notes,
        ];

        foreach ($this->headerFields as $field) {
            $ownedByCategory = $resolved->contains(fn ($f) => $f->fieldKey === $field->fieldKey);
            $row[] = $ownedByCategory && $valuesByKey->has($field->fieldKey)
                ? $valuesByKey->get($field->fieldKey)->rawValue()
                : '';
        }

        return $row;
    }

    /**
     * @return Collection<int, \App\Services\DynamicFields\ResolvedField>
     */
    private function resolveHeaderFields(): Collection
    {
        $fields = collect();

        foreach ($this->assets->pluck('category_id')->unique() as $categoryId) {
            foreach ($this->fields->resolveForCategory($categoryId) as $field) {
                if (! $fields->contains(fn ($f) => $f->fieldKey === $field->fieldKey)) {
                    $fields->push($field);
                }
            }
        }

        return $fields->sortBy('displayOrder')->values();
    }
}
