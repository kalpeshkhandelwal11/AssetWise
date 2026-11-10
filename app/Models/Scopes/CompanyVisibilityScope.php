<?php

namespace App\Models\Scopes;

use App\Support\CompanyVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope on Asset: a restricted user only sees assets owned by, or operated by, one of
 * their companies. Every model that hangs off an asset (movements, disposals, maintenance,
 * AMC/warranty, depreciation, audit items…) inherits this through
 * App\Models\Concerns\ScopedByAssetCompany, which uses whereHas('asset') — Eloquent applies
 * this scope inside that subquery, so each UNION leg is filtered independently.
 */
class CompanyVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ids = CompanyVisibility::ids();

        if ($ids === null) {
            return;
        }

        $table = $model->getTable();

        $builder->where(fn (Builder $q) => $q
            ->whereIn("$table.company_id", $ids)
            ->orWhereIn("$table.operating_company_id", $ids));
    }
}
