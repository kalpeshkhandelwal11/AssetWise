<?php

namespace App\Services;

use App\Exports\AmcWarrantyExport;
use App\Exports\AssetAgingExport;
use App\Exports\AssetExport;
use App\Exports\AuditCampaignExport;
use App\Exports\AuditComplianceExport;
use App\Exports\DepreciationReportExport;
use App\Exports\DisposalReportExport;
use App\Exports\InterCompanyTransferExport;
use App\Exports\MaintenanceReportExport;
use App\Exports\MovementReportExport;
use App\Exports\UtilizationReportExport;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetStatusHistory;
use App\Models\AuditItem;
use App\Models\Company;
use App\Models\DepreciationScheduleLine;
use App\Models\DisposalRequest;
use App\Models\MaintenanceRecord;
use App\Models\Reports\CoverageContract;
use App\Models\WarrantyRecord;
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
            'maintenance'            => new MaintenanceReportExport($filters, $this),
            'amc_warranty'           => new AmcWarrantyExport($filters, $this),
            'aging'                  => new AssetAgingExport($filters, $this),
            'utilization'            => new UtilizationReportExport($filters, $this),
            'audit_compliance'       => new AuditComplianceExport($filters, $this),
            'audit_campaign'          => new AuditCampaignExport($filters, $this),
            'depreciation_schedule'  => new DepreciationReportExport($filters, $this),
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
            'maintenance'            => $this->buildMaintenanceQuery($filters),
            'amc_warranty'           => $this->buildAmcWarrantyQuery($filters),
            'aging'                  => $this->buildAgingQuery($filters),
            'audit_compliance'       => $this->buildAuditComplianceQuery($filters),
            'audit_campaign'          => $this->buildAuditCampaignQuery($filters),
            'depreciation_schedule'  => $this->buildDepreciationScheduleQuery($filters),
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

    public function buildMaintenanceQuery(array $filters): Builder
    {
        $query = MaintenanceRecord::query()->with([
            'asset.company', 'asset.category', 'maintenanceType', 'loggedBy',
        ]);

        $this->scopeByRelatedAssetCompany($query, $this->intOrNull($filters, 'company_id'));

        foreach (['maintenance_type_id', 'status'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (isset($filters['is_capitalized']) && $filters['is_capitalized'] !== '') {
            $query->where('is_capitalized', (bool) $filters['is_capitalized']);
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->whereHas('asset', fn ($q) => $q
                ->where('asset_tag', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%"));
        }

        // performed_date, not created_at: a maintenance record is scheduled on one date and
        // performed on another, and "show me March's servicing" means work DONE in March.
        // This implicitly excludes 'scheduled' rows (performed_date is null) whenever a
        // date range is applied — correct for a service-history report.
        $this->applyDateRange($query, $filters, 'performed_date');

        return $query->latest('id');
    }

    /**
     * One row per coverage contract, AMC and warranty combined via UNION with a 'kind'
     * discriminator — the two tables have near-identical semantics (per-asset coverage
     * window) but different column names and no shared parent table.
     *
     * TRAP, verified during planning: a whereHas()/filter applied to a builder BEFORE
     * ->union() constrains only that first leg — rows from the unioned leg sail through
     * unfiltered, which is a cross-company data leak on a company-scoped report. Every
     * filter here goes through applyCoverageFilters() called once per leg, never once on
     * the combined builder, so the two legs cannot drift out of sync.
     */
    public function buildAmcWarrantyQuery(array $filters): Builder
    {
        $kind = $filters['kind'] ?? null;

        $amc = CoverageContract::query()->selectRaw(
            "id, asset_id, 'amc' as kind, vendor as provider_name, coverage as terms_text, start_date, end_date, cost"
        );
        $this->applyCoverageFilters($amc, $filters, 'vendor');

        $warranty = WarrantyRecord::query()->selectRaw(
            "id, asset_id, 'warranty' as kind, provider as provider_name, terms as terms_text, start_date, end_date, null as cost"
        );
        $this->applyCoverageFilters($warranty, $filters, 'provider');

        $query = match ($kind) {
            'amc'      => $amc,
            'warranty' => $warranty,
            default    => $amc->union($warranty),
        };

        return $query->with('asset.company')->orderBy('end_date');
    }

    private function applyCoverageFilters(Builder $query, array $filters, string $providerColumn): void
    {
        $this->scopeByRelatedAssetCompany($query, $this->intOrNull($filters, 'company_id'));

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($q) => $q
                ->where($providerColumn, 'like', "%$s%")
                ->orWhereHas('asset', fn ($aq) => $aq
                    ->where('asset_tag', 'like', "%$s%")
                    ->orWhere('name', 'like', "%$s%")));
        }

        // The "coverage window" — end_date is the meaningful business date here, not
        // created_at, mirroring buildMaintenanceQuery()'s performed_date choice.
        $this->applyDateRange($query, $filters, 'end_date');

        $today = now()->startOfDay();
        $expiringBy = now()->addDays(ExpiryAlertService::THRESHOLD_DAYS[0])->startOfDay();

        match ($filters['expiry_status'] ?? null) {
            'expired'  => $query->whereDate('end_date', '<', $today),
            'expiring' => $query->whereDate('end_date', '>=', $today)->whereDate('end_date', '<=', $expiringBy),
            'active'   => $query->whereDate('end_date', '>', $expiringBy),
            default    => null,
        };
    }

    /** Coverage-window status for a single AMC/warranty row — shared by screen, Excel, PDF, mirrors ageBucket(). */
    public static function expiryStatus(?\Illuminate\Support\Carbon $endDate): string
    {
        if (! $endDate) {
            return 'unknown';
        }

        $today = now()->startOfDay();
        $expiringBy = now()->addDays(ExpiryAlertService::THRESHOLD_DAYS[0])->startOfDay();

        return match (true) {
            $endDate->lt($today)       => 'expired',
            $endDate->lte($expiringBy) => 'expiring',
            default                    => 'active',
        };
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

    public function buildDepreciationScheduleQuery(array $filters): Builder
    {
        $query = DepreciationScheduleLine::query()->with([
            'asset.company', 'asset.category', 'asset.department', 'setting.method',
        ]);

        $this->scopeByRelatedAssetCompany($query, $this->intOrNull($filters, 'company_id'));

        foreach (['category_id', 'department_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->whereHas('asset', fn ($q) => $q->where($filter, $filters[$filter]));
            }
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

        return $query->orderBy('asset_id')->orderBy('period_year')->orderBy('period_month');
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

    public function buildAuditCampaignQuery(array $filters): Builder
    {
        $query = AuditItem::query()->with([
            'campaign.auditType', 'asset.company', 'expectedLocation', 'expectedCustodian', 'verifiedBy',
        ]);

        $this->scopeByRelatedAssetCompany($query, $this->intOrNull($filters, 'company_id'));

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
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

        $this->applyDateRange($query, $filters, 'verified_at');

        return $query->latest('id');
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
