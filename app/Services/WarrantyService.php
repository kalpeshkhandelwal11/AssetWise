<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use App\Models\WarrantyRecord;

/**
 * Warranty records are full history (multiple per asset supported — e.g. manufacturer
 * warranty plus a later extended-warranty purchase); assets.warranty_expiry stays a
 * single quick-alert column synced to the furthest end_date across all of an asset's
 * warranty records — cleared back to null when the last one is removed.
 */
class WarrantyService
{
    public function create(Asset $asset, array $data, User $actor): WarrantyRecord
    {
        $record = WarrantyRecord::create($data + [
            'asset_id'   => $asset->id,
            'created_by' => $actor->id,
        ]);

        $this->syncAssetWarrantyExpiry($asset);

        return $record;
    }

    public function update(WarrantyRecord $record, array $data): WarrantyRecord
    {
        $record->update($data);
        $this->syncAssetWarrantyExpiry($record->asset);

        return $record;
    }

    public function delete(WarrantyRecord $record): void
    {
        $asset = $record->asset;
        $record->delete();
        $this->syncAssetWarrantyExpiry($asset);
    }

    private function syncAssetWarrantyExpiry(Asset $asset): void
    {
        $asset->update(['warranty_expiry' => $asset->warrantyRecords()->max('end_date')]);
    }
}
