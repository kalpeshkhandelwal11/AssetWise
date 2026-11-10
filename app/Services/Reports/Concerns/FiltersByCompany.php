<?php

namespace App\Services\Reports\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared company-scoping helpers for DashboardService and ReportService. Each helper
 * no-ops when $companyId is null, so every call site can pass the filter unconditionally.
 */
trait FiltersByCompany
{
    /** Direct company_id column on the query's own table (Asset, Aging, Utilization). */
    protected function scopeByCompany(Builder $query, ?int $companyId, string $column = 'company_id'): Builder
    {
        if ($companyId !== null) {
            $query->where($column, $companyId);
        }

        return $query;
    }

    /**
     * NHAI-owned (external) assets are not ours: keep them out of asset registers, aging,
     * utilization and the dashboard unless the report explicitly asks (include_external) or the
     * user picked that external company as the filter.
     */
    protected function excludeExternalOwners(Builder $query, array $filters = [], ?string $assetRelation = null): Builder
    {
        if (! empty($filters['include_external'])) {
            return $query;
        }

        $companyId = $filters['company_id'] ?? null;
        if ($companyId && \App\Models\Company::find($companyId)?->isExternal()) {
            return $query;
        }

        $relation = $assetRelation ? "{$assetRelation}.company" : 'company';

        return $query->whereHas($relation, fn (Builder $q) => $q->where('party_type', 'internal'));
    }

    /**
     * For tables with no company_id of their own that hang off Asset (Disposal Report,
     * Audit/Compliance via AssetStatusHistory).
     */
    protected function scopeByRelatedAssetCompany(Builder $query, ?int $companyId, string $relation = 'asset'): Builder
    {
        if ($companyId !== null) {
            $query->whereHas($relation, fn (Builder $q) => $q->where('company_id', $companyId));
        }

        return $query;
    }

    /**
     * Movement/Inter-Company Transfer rows: a company shows up whether it's the source
     * or destination of the movement.
     */
    protected function scopeByMovementCompany(Builder $query, ?int $companyId): Builder
    {
        if ($companyId !== null) {
            $query->where(fn (Builder $q) => $q
                ->where('from_company_id', $companyId)
                ->orWhere('to_company_id', $companyId));
        }

        return $query;
    }
}
