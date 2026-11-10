<?php

namespace Tests\Concerns;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\User;
use App\Services\MovementService;
use App\Support\CompanyVisibility;

/**
 * Phase 2: physical movements (Transfer / Return / Inter-Company) only DISPATCH on approval;
 * they land when received. Tests that assert the post-move state call this after approving.
 */
trait ReceivesMovements
{
    /** Receive every in-transit movement of $asset (or all, when null) as a stores user. */
    protected function receiveInTransit(?Asset $asset = null, ?User $receiver = null): void
    {
        $receiver ??= User::factory()->create();

        CompanyVisibility::unrestricted(function () use ($asset, $receiver) {
            AssetMovement::query()
                ->where('receipt_status', 'in_transit')
                ->when($asset, fn ($q) => $q->where('asset_id', $asset->id))
                ->get()
                ->each(fn (AssetMovement $m) => app(MovementService::class)
                    ->receive($m, $receiver, ['condition' => 'ok', 'method' => 'on_behalf']));
        });
    }
}
