<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;

class AssetService
{
    public function __construct(
        private readonly AssetNamingService $naming,
        private readonly AssetCoverageSync $coverage,
    ) {
    }

    public function create(array $data, User $actor): Asset
    {
        $data['created_by'] = $actor->id;

        // Auto-number the asset code from the category-wise naming series unless one was supplied.
        if (empty($data['asset_tag']) && $this->naming->enabled()) {
            $category = ! empty($data['category_id']) ? AssetCategory::find($data['category_id']) : null;
            $data['asset_tag'] = $this->naming->next($category);
        }

        $asset = Asset::create($data);

        // A warranty / AMC date typed at onboarding becomes the asset's first coverage record.
        $this->coverage->recordFromAsset($asset, $actor);

        return $asset;
    }

    public function update(Asset $asset, array $data, User $actor): Asset
    {
        $data['updated_by'] = $actor->id;

        // Once coverage records exist the expiry column is derived from them (WarrantyService /
        // AmcService keep it at the latest end date) — a date posted from the asset form must
        // not overwrite it, or the column and the records disagree.
        if ($this->coverage->hasWarrantyRecords($asset)) {
            unset($data['warranty_expiry']);
        }
        if ($this->coverage->hasAmcContracts($asset)) {
            unset($data['amc_expiry']);
        }

        $asset->update($data);

        $this->coverage->recordFromAsset($asset, $actor);

        return $asset;
    }

    /**
     * Why this asset may not be deleted right now, or null when it may. Deleting an asset with
     * something open would orphan it: a pending request left in approvers' inboxes, a movement
     * that can never be received, a disposal that can never be completed.
     */
    public function deletionBlocker(Asset $asset): ?string
    {
        return match (true) {
            $asset->hasPendingCreationApproval()     => 'its creation approval is still pending — reject it first',
            $asset->hasPendingMovement()             => 'it has a movement pending approval',
            $asset->isInTransit() || $asset->openTransitMovement() !== null => 'it is in transit — receive it or resolve the short receipt first',
            $asset->hasPendingDisposal()             => 'it has a disposal in progress',
            $asset->hasPendingLoss()                 => 'it has a loss write-off pending approval',
            $asset->hasPendingHandover()             => 'it is on a plaza handover pending approval',
            $asset->hasPendingDepreciationRequest()  => 'it has a depreciation change pending approval',
            default                                  => null,
        };
    }

    public function delete(Asset $asset): void
    {
        if ($reason = $this->deletionBlocker($asset)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'asset' => "\"{$asset->name}\" cannot be deleted: {$reason}.",
            ]);
        }

        $asset->delete();
    }
}
