<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;

class AssetService
{
    public function create(array $data, User $actor): Asset
    {
        $data['created_by'] = $actor->id;

        return Asset::create($data);
    }

    public function update(Asset $asset, array $data, User $actor): Asset
    {
        $data['updated_by'] = $actor->id;

        $asset->update($data);

        return $asset;
    }

    public function delete(Asset $asset): void
    {
        $asset->delete();
    }
}
