<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetStatus;
use App\Models\AssetStatusHistory;
use App\Models\MovementType;

/**
 * An asset that goes live already holding a custodian (set on the create form, in an import
 * row or on a takeover) was assigned without a movement — so its movement history started
 * blank and it could sit "Available" while in someone's hands.
 *
 * This writes that first assignment as a completed movement and aligns the status. It needs no
 * approval of its own: the asset's creation (or its import batch / takeover) was the approval.
 * Idempotent — an asset that already has a movement naming a custodian is left alone.
 */
class OpeningAssignmentService
{
    public const NOTE = 'Opening assignment — custodian set when the asset was created.';

    public function record(Asset $asset, ?int $actorId = null): ?AssetMovement
    {
        $actorId ??= $asset->created_by;

        if (! $asset->custodian_id || ! $actorId || $asset->isDraft() || $asset->isRetired()) {
            return null;
        }

        if (AssetMovement::withoutGlobalScopes()->where('asset_id', $asset->id)->whereNotNull('to_custodian_id')->exists()) {
            return null;
        }

        // Available-with-a-custodian is a contradiction; Assigned is what a movement would set.
        $assigned = AssetStatus::where('code', 'ASSIGNED')->first();
        if ($assigned && $asset->status?->code === 'AVAILABLE') {
            AssetStatusHistory::create([
                'asset_id'       => $asset->id,
                'from_status_id' => $asset->status_id,
                'to_status_id'   => $assigned->id,
                'changed_by'     => $actorId,
                'reason'         => 'Assigned at creation',
                'created_at'     => now(),
            ]);
            $asset->update(['status_id' => $assigned->id]);
        }

        $type = MovementType::where('code', 'ASSIGNMENT')->first();
        if (! $type) {
            return null;
        }

        return AssetMovement::create([
            'asset_id'         => $asset->id,
            'movement_type_id' => $type->id,
            'to_custodian_id'  => $asset->custodian_id,
            'to_location_id'   => $asset->location_id,
            'to_department_id' => $asset->department_id,
            'status'           => 'completed',
            'notes'            => self::NOTE,
            'requested_by'     => $actorId,
        ]);
    }
}
