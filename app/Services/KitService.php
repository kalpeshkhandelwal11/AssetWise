<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Kit;
use App\Models\KitAsset;
use App\Models\KitItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Kit template CRUD (M17). Plain `kits.manage`-gated — no approval workflow; the workflow
 * only enters when a kit is *assigned* (KitAssignmentService).
 */
class KitService
{
    public function create(array $data, User $actor): Kit
    {
        return Kit::create([
            'name'        => $data['name'],
            'code'        => $data['code'],
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
            'created_by'  => $actor->id,
        ]);
    }

    public function update(Kit $kit, array $data): Kit
    {
        $kit->update($data);

        return $kit;
    }

    public function addItem(Kit $kit, array $data): KitItem
    {
        return $kit->items()->create([
            'label'       => $data['label'],
            'category_id' => $data['category_id'] ?? null,
            'quantity'    => $data['quantity'] ?? 1,
            'sort_order'  => $data['sort_order'] ?? $kit->items()->count(),
        ]);
    }

    public function updateItem(KitItem $item, array $data): KitItem
    {
        $item->update($data);

        return $item;
    }

    public function deleteItem(KitItem $item): void
    {
        $item->delete();
    }

    /** Link a physical asset to a slot, enforcing the slot's optional category constraint. */
    public function linkAsset(KitItem $item, Asset $asset): KitAsset
    {
        if ($item->category_id && (int) $asset->category_id !== (int) $item->category_id) {
            throw ValidationException::withMessages([
                'asset_id' => "\"{$asset->name}\" doesn't match this slot's required category.",
            ]);
        }

        return KitAsset::firstOrCreate([
            'kit_item_id' => $item->id,
            'asset_id'    => $asset->id,
        ]);
    }

    public function unlinkAsset(KitAsset $kitAsset): void
    {
        $kitAsset->delete();
    }

    /** A kit is "ready" when every slot has at least `quantity` assets linked. */
    public function isReady(Kit $kit): bool
    {
        $kit->loadMissing('items.kitAssets');

        if ($kit->items->isEmpty()) {
            return false;
        }

        return $kit->items->every(fn (KitItem $item) => $item->kitAssets->count() >= $item->quantity);
    }
}
