<?php

namespace App\Models\Concerns;

use App\Support\CompanyVisibility;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company isolation for models that belong to an Asset via an `asset()` relation.
 *
 * whereHas('asset') applies Asset's CompanyVisibilityScope inside the subquery. withTrashed()
 * keeps history of soft-deleted assets visible to the companies that owned them.
 *
 * A model can override companyVisibilityConstraint() to widen the rule (AssetMovement also
 * matches its from/to company so both sides of an inter-company transfer see it).
 */
trait ScopedByAssetCompany
{
    public static function bootScopedByAssetCompany(): void
    {
        static::addGlobalScope('company_visibility', function (Builder $builder) {
            $ids = CompanyVisibility::ids();

            if ($ids === null) {
                return;
            }

            (new static)->companyVisibilityConstraint($builder, $ids);
        });
    }

    /** @param int[] $ids */
    protected function companyVisibilityConstraint(Builder $builder, array $ids): void
    {
        $builder->whereHas('asset', fn (Builder $q) => $q->withTrashed());
    }
}
