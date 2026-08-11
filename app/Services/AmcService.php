<?php

namespace App\Services;

use App\Models\AmcContract;
use App\Models\Asset;
use App\Models\User;

/**
 * AMC contracts are full history (multiple per asset supported); assets.amc_expiry stays
 * a single quick-alert column synced to the furthest end_date across all of an asset's
 * contracts — cleared back to null when the last one is removed.
 */
class AmcService
{
    public function create(Asset $asset, array $data, User $actor): AmcContract
    {
        $contract = AmcContract::create($data + [
            'asset_id'   => $asset->id,
            'created_by' => $actor->id,
        ]);

        $this->syncAssetAmcExpiry($asset);

        return $contract;
    }

    public function update(AmcContract $contract, array $data): AmcContract
    {
        $contract->update($data);
        $this->syncAssetAmcExpiry($contract->asset);

        return $contract;
    }

    public function delete(AmcContract $contract): void
    {
        $asset = $contract->asset;
        $contract->delete();
        $this->syncAssetAmcExpiry($asset);
    }

    private function syncAssetAmcExpiry(Asset $asset): void
    {
        $asset->update(['amc_expiry' => $asset->amcContracts()->max('end_date')]);
    }
}
