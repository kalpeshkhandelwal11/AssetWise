<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetStatusHistory;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintenance records are plain permission-gated CRUD (maintenance.manage) — unlike
 * M09/M13 there is no approval workflow here (M11 depends only on M03). The one piece
 * of cross-cutting behavior is the asset status auto-flip: starting an in-progress
 * record flips the asset to the MAINTENANCE status and restores its prior status on
 * completion/cancellation, mirroring the AssetStatusHistory ledger pattern DisposalService
 * uses for its own terminal status flip.
 */
class MaintenanceService
{
    public function __construct(private DepreciationService $depreciation)
    {
    }

    public function log(Asset $asset, array $data, User $actor): MaintenanceRecord
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $record = MaintenanceRecord::create($data + [
                'asset_id'  => $asset->id,
                'logged_by' => $actor->id,
            ]);

            if ($record->status === 'in_progress') {
                $this->startMaintenance($asset, $record, $actor);
            } elseif ($record->status === 'completed') {
                $this->capitalizeIfFlagged($record, $actor);
            }

            return $record;
        });
    }

    public function update(MaintenanceRecord $record, array $data, User $actor): MaintenanceRecord
    {
        return DB::transaction(function () use ($record, $data, $actor) {
            $previousStatus = $record->status;
            $record->update($data);
            $newStatus = $record->status;

            if ($previousStatus !== 'in_progress' && $newStatus === 'in_progress') {
                $this->startMaintenance($record->asset, $record, $actor);
            } elseif ($previousStatus === 'in_progress' && in_array($newStatus, ['completed', 'cancelled'], true)) {
                $this->restoreAssetStatus($record->asset, $record, $actor);
            }

            if ($previousStatus !== 'completed' && $newStatus === 'completed') {
                $this->capitalizeIfFlagged($record, $actor);
            }

            return $record;
        });
    }

    public function delete(MaintenanceRecord $record): void
    {
        if ($record->status === 'in_progress') {
            throw ValidationException::withMessages([
                'status' => 'Cannot delete a maintenance record that is currently in progress — complete or cancel it first.',
            ]);
        }

        $record->delete();
    }

    /** Sum of logged repair/maintenance costs for one asset — the "repair-cost rollup" acceptance criterion. */
    public function costRollup(Asset $asset): float
    {
        return (float) $asset->maintenanceRecords()->sum('cost');
    }

    /**
     * M16 bridge: a completed record flagged for capitalization adds its capitalized amount to
     * the asset's depreciation cost basis and extends the useful life. The actual change is
     * approval-gated, so this just raises the depreciation request (no-op if the asset isn't
     * being depreciated or nothing was capitalized).
     */
    private function capitalizeIfFlagged(MaintenanceRecord $record, User $actor): void
    {
        if (! $record->is_capitalized || (float) $record->capitalized_amount <= 0) {
            return;
        }

        $this->depreciation->capitalize($record, $actor);
    }

    private function startMaintenance(Asset $asset, MaintenanceRecord $record, User $actor): void
    {
        $maintenanceStatus = AssetStatus::where('code', 'MAINTENANCE')->first();

        if (! $maintenanceStatus || $maintenanceStatus->id === $asset->status_id) {
            return;
        }

        $record->update(['previous_status_id' => $asset->status_id]);

        AssetStatusHistory::create([
            'asset_id'       => $asset->id,
            'from_status_id' => $asset->status_id,
            'to_status_id'   => $maintenanceStatus->id,
            'changed_by'     => $actor->id,
            'reason'         => 'Maintenance started',
            'created_at'     => now(),
        ]);

        $asset->update(['status_id' => $maintenanceStatus->id]);
    }

    private function restoreAssetStatus(Asset $asset, MaintenanceRecord $record, User $actor): void
    {
        if (! $record->previous_status_id || $record->previous_status_id === $asset->status_id) {
            return;
        }

        AssetStatusHistory::create([
            'asset_id'       => $asset->id,
            'from_status_id' => $asset->status_id,
            'to_status_id'   => $record->previous_status_id,
            'changed_by'     => $actor->id,
            'reason'         => 'Maintenance ' . $record->status,
            'created_at'     => now(),
        ]);

        $asset->update(['status_id' => $record->previous_status_id]);
    }
}
