<?php

namespace App\Services;

use App\Models\AmcContract;
use App\Models\Asset;
use App\Models\User;
use App\Models\WarrantyRecord;

/**
 * One source of truth for warranty / AMC coverage.
 *
 * `assets.warranty_expiry` / `assets.amc_expiry` are a cached "latest end date" that
 * WarrantyService / AmcService keep in sync with the asset's records. The asset form, bulk
 * import and plaza takeover also let a date be typed straight onto the asset at onboarding —
 * which on its own left a date that drove alerts but appeared in no AMC / warranty list or
 * report, and was silently overwritten (or wiped) by the first real record.
 *
 * This turns such a typed date into the asset's first coverage record, so the date lives in
 * exactly one place. Idempotent: an asset that already has a record of that kind is left alone
 * (its column is derived from the records from then on — see AssetService::update()).
 */
class AssetCoverageSync
{
    /** Marks records created from an onboarding date rather than entered as a contract. */
    public const NOTE = 'Recorded from the expiry date entered at asset onboarding.';

    /** @return int number of records created (0–2) */
    public function recordFromAsset(Asset $asset, ?User $actor = null): int
    {
        $actorId = $actor?->id ?? $asset->created_by;
        if (! $actorId) {
            return 0; // created_by is a required FK — nothing to attribute the record to
        }

        $created = 0;
        $purchased = $asset->purchase_date;

        // Model-level queries without global scopes: this runs from queued imports and the
        // backfill command too, where company visibility must not hide the asset's own rows.
        $warrantyEnd = $asset->warranty_expiry;
        if ($warrantyEnd && ! WarrantyRecord::withoutGlobalScopes()->where('asset_id', $asset->id)->exists()) {
            WarrantyRecord::create([
                'asset_id'   => $asset->id,
                'provider'   => $asset->manufacturer ?: ($asset->vendor ?: 'Not specified'),
                'start_date' => $purchased && $purchased->lte($warrantyEnd) ? $purchased : null,
                'end_date'   => $warrantyEnd,
                'terms'      => self::NOTE,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        $amcEnd = $asset->amc_expiry;
        if ($amcEnd && ! AmcContract::withoutGlobalScopes()->where('asset_id', $asset->id)->exists()) {
            // start_date is required on AMC contracts and must not fall after the end date.
            $start = $purchased && $purchased->lte($amcEnd) ? $purchased : $amcEnd->copy()->min(now()->startOfDay());
            AmcContract::create([
                'asset_id'   => $asset->id,
                'vendor'     => $asset->vendor ?: 'Not specified',
                'start_date' => $start,
                'end_date'   => $amcEnd,
                'coverage'   => self::NOTE,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    /** Whether the asset's warranty / AMC date is now owned by its records (not editable on the asset). */
    public function hasWarrantyRecords(Asset $asset): bool
    {
        return $asset->exists && WarrantyRecord::withoutGlobalScopes()->where('asset_id', $asset->id)->exists();
    }

    public function hasAmcContracts(Asset $asset): bool
    {
        return $asset->exists && AmcContract::withoutGlobalScopes()->where('asset_id', $asset->id)->exists();
    }
}
