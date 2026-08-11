<?php

namespace App\Services;

use App\Exports\AssetAgingExport;
use App\Exports\AssetExport;
use App\Exports\AuditComplianceExport;
use App\Exports\DisposalReportExport;
use App\Exports\InterCompanyTransferExport;
use App\Exports\MovementReportExport;
use App\Exports\UtilizationReportExport;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetStatusHistory;
use App\Models\Company;
use App\Models\DisposalRequest;
use App\Services\Reports\Concerns\FiltersByCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The single place each report's filter/column logic lives. ReportController (screen)
 * and every report Export class both call in here, so "exports match on-screen filters"
 * holds by construction rather than by discipline.
 */
class ReportService
{
    use FiltersByCompany;

    public function __construct(private readonly DynamicFieldService $fields)
    {
    }

    /** Resolves the Excel export object for a report type — used by ReportController@export and GenerateReportExport. */
    public function exportFor(string $type, array $filters): object
    {
        return match ($type) {
            'asset_register'        => new AssetExport($filters, $this->fields),
            'movement'               => new MovementReportExport($filters, $this),
            'intercompany_transfer'  => new InterCompanyTransferExport($filters, $this),
            'disposal'               => new DisposalReportExport($filters, $this),
            'aging'                  => new AssetAgingExport($filters, $this),
            'utilization'            => new UtilizationReportExport($filters, $this),
            'audit_compliance'       => new AuditComplianceExport($filters, $this),
            default => throw new \InvalidArgumentException("Report type \"{$type}\" has no export class."),
        };
    }

    public function queryFor(string $type, array $filters): Builder
    {
        return match ($type) {
            'asset_register'        => $this->buildAssetRegisterQuery($filters),
            'movement'               => $this->buildMovementQuery($filters),
            'intercompany_transfer'  => $this->buildInterCompanyTransferQuery($filters),
            'disposal'               => $this->buildDisposalQuery($filters),
            'aging'                  => $this->buildAgingQuery($filters),
            'audit_compliance'       => $this->buildAuditComplianceQuery($filters),
            default => throw new \InvalidArgumentException("Report type \"{$type}\" has no query builder."),
        };
    }

    public function buildAssetRegisterQuery(array $filters): Builder
    {
        $query = Asset::query()->with([
            'company', 'category', 'assetType', 'status', 'location', 'custodian',
            'department', 'branch', 'fieldValues.categoryField',
        ]);

        $this->scopeByCompany($query, $this->intOrNull($filters, 'company_id'));

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($q) => $q
                ->where('asset_tag', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%")
                ->orWhere('serial_number', 'like', "%$s%"));
        }

        foreach (['category_id', 'asset_type_id', 'status_id', 'location_id', 'department_id', 'branch_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        return $query->orderBy('name');
    }

    public function buildMovementQuery(array $filters): Builder
    {
        $query = AssetMovement::query()->with([
            'asset.company', 'asset.category', 'movementType',
            'fromCompany', 'toCompany', 'fromLocation', 'toLocation',
            'fromCustodian', 'toCustodian', 'fromDepartment', 'toDepartment',
            'toStatus', 'requestedBy', 'verifiedBy',
        ]);

        $this->scopeByMovementCompany($query, $this->intOrNull($filters, 'company_id'));
        $this->applyMovementCommonFilters($query, $filters);

        return $query->latest('id');
    }

    public function buildInterCompanyTransferQuery(array $filters): Builder
    {
        $query = AssetMovement::query()
            ->whereHas('movementType', fn ($q) => $q->where('code', 'INTER_COMPANY_TRANSFER'))
            ->with([
                'asset.company', 'asset.category', 'movementType',
                'fromCompany', 'toCompany', 'requestedBy', 'verifiedBy',
                'approvalRequest.actions.user',
            ]);

        if (! empty($filters['from_company_id'])) {
            $query->where('from_company_id', $filters['from_company_id']);
        }

        if (! empty($filters['to_company_id'])) {
            $query->where('to_company_id', $filters['to_company_id']);
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->whereHas('asset', fn ($q) => $q
                ->where('asset_tag', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%"));
        }

        $this->applyDateRange($query, $filters, 'created_at');

        return $query->latest('id');
    }

    public function buildDisposalQuery(array $filters): Builder
    {
        $query = DisposalRequest::query()->with([
            'asset.company', 'asset.category', 'disposalType', 'requestedBy', 'writtenOffBy', 'scrappedBy',
        ]);

        $this->scopeByRelatedAssetCompany($query, $this->intOrNull($filters, 'company_id'));

        if (! empty($filters['disposal_type_id'])) {
            $query->where('disposal_type_id', $filters['disposal_type_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->whereHas('asset', fn ($q) => $q
                ->where('asset_tag', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%"));
        }

        $this->applyDateRange($query, $filters, 'created_at');

        return $query->latest('id');
    }

    public function buildAgingQuery(array $filters): Builder
    {
        $query = Asset::query()->with(['company', 'category', 'status'])->whereNotNull('purchase_date');

        $this->scopeByCompany($query, $this->intOrNull($filters, 'company_id'));

        foreach (['category_id', 'status_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        return $query->orderBy('purchase_date');
    }

    /** Age bucket for a single asset row — shared by the screen view, Excel, and PDF exports. */
    public static function ageBucket(?\Illuminate\Support\Carbon $purchaseDate): string
    {
        if (! $purchaseDate) {
            return 'Unknown';
        }

        $years = $purchaseDate->diffInYears(now());

        return match (true) {
            $years < 1 => '0–1y',
            $years < 3 => '1–3y',
            $years < 5 => '3–5y',
            default    => '5y+',
        };
    }

    /**
     * One row per company: Total / Assigned / Available / In Maintenance / Utilization %.
     * Not a Builder — this report is an aggregate, reshaped here rather than in SQL.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildUtilizationRows(array $filters): Collection
    {
        $companyId = $this->intOrNull($filters, 'company_id');

        $raw = Asset::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->selectRaw('company_id, status_id, count(*) as total')
            ->groupBy('company_id', 'status_id')
            ->with('status')
            ->get()
            ->groupBy('company_id');

        return Company::query()
            ->when($companyId, fn ($q) => $q->where('id', $companyId))
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) use ($raw) {
                $rows = $raw->get($company->id, collect());
                $total = $rows->sum('total');
                $assigned = $rows->filter(fn ($r) => $r->status?->code === 'ASSIGNED')->sum('total');
                $available = $rows->filter(fn ($r) => $r->status?->code === 'AVAILABLE')->sum('total');
                $maintenance = $rows->filter(fn ($r) => $r->status?->code === 'MAINTENANCE')->sum('total');

                return [
                    'company'          => $company,
                    'total'            => $total,
                    'assigned'         => $assigned,
                    'available'        => $available,
                    'in_maintenance'   => $maintenance,
                    'utilization_pct'  => $total > 0 ? round($assigned / $total * 100, 1) : 0.0,
                ];
            });
    }

    public function buildAuditComplianceQuery(array $filters): Builder
    {
        $query = AssetStatusHistory::query()->with([
            'asset.company', 'asset.category', 'fromStatus', 'toStatus', 'changedBy',
        ]);

        $this->scopeByRelatedAssetCompany($query, $this->intOrNull($filters, 'company_id'));

        if (! empty($filters['status'])) {
            $query->where(fn ($q) => $q
                ->where('from_status_id', $filters['status'])
                ->orWhere('to_status_id', $filters['status']));
        }

        if (! empty($filters['changed_by'])) {
            $query->where('changed_by', $filters['changed_by']);
        }

        $this->applyDateRange($query, $filters, 'created_at');

        return $query->latest('created_at');
    }

    private function applyMovementCommonFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['movement_type_id'])) {
            $query->where('movement_type_id', $filters['movement_type_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->whereHas('asset', fn ($q) => $q
                ->where('asset_tag', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%"));
        }

        $this->applyDateRange($query, $filters, 'created_at');
    }

    private function applyDateRange(Builder $query, array $filters, string $column): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    private function intOrNull(array $filters, string $key): ?int
    {
        return ! empty($filters[$key]) ? (int) $filters[$key] : null;
    }
}
