<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\KitAssignment;
use App\Models\MovementType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigns / returns a kit or ad-hoc bundle (M17) by driving M09's MovementService.
 *
 * The single-vs-per_asset approval mode (global `kit_assignment_approval_mode` setting) decides
 * the shape:
 *  - single   → one AssetMovementBatch + one approval on the KitAssignment ('kit_assignment' workflow)
 *  - per_asset → one MovementService::submit() per asset, each its own 'transfer' approval
 */
class KitAssignmentService
{
    public function __construct(
        private MovementService $movements,
        private WorkflowService $workflows,
    ) {
    }

    /** @param \Illuminate\Support\Collection<int, \App\Models\Asset>|Collection $assets */
    public function submit(array $data, $assets, User $actor): KitAssignment
    {
        return $this->createAssignment($data, $assets, $actor, 'out');
    }

    /** Reverse a completed 'out' assignment: RETURN every asset it moved, in one action. */
    public function returnKit(KitAssignment $original, User $actor): KitAssignment
    {
        if ($original->direction !== 'out' || $original->status !== 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Only a completed kit assignment can be returned.',
            ]);
        }

        // Fetch as an Eloquent collection (buildBatch/submit expect one), deduped by asset.
        $assets = Asset::whereIn('id', $original->movements()->pluck('asset_id')->unique())->get();

        $returnType = MovementType::where('code', 'RETURN')->firstOrFail();

        return $this->createAssignment(
            ['movement_type_id' => $returnType->id],
            $assets,
            $actor,
            'return',
            $original,
        );
    }

    private function createAssignment(array $data, $assets, User $actor, string $direction, ?KitAssignment $parent = null): KitAssignment
    {
        $mode = Setting::get('kit_assignment_approval_mode', 'single') === 'per_asset' ? 'per_asset' : 'single';

        return DB::transaction(function () use ($data, $assets, $actor, $direction, $parent, $mode) {
            $assignment = KitAssignment::create([
                'kit_id'               => $data['kit_id'] ?? null,
                'direction'            => $direction,
                'parent_assignment_id' => $parent?->id,
                'movement_type_id'     => $data['movement_type_id'],
                'to_company_id'        => $data['to_company_id'] ?? null,
                'to_custodian_id'      => $data['to_custodian_id'] ?? null,
                'to_location_id'       => $data['to_location_id'] ?? null,
                'to_department_id'     => $data['to_department_id'] ?? null,
                'approval_mode'        => $mode,
                'status'               => 'pending_approval',
                'requested_by'         => $actor->id,
            ]);

            if ($mode === 'single') {
                // buildBatch() runs the per-asset guards + creates the batch/movements; the
                // KitAssignment (not the batch) is the approvable for the kit_assignment workflow.
                $this->movements->buildBatch($assets, $data, $actor, $assignment->id);
                $approval = $this->workflows->submit($assignment, 'kit_assignment', $actor);
                $assignment->update(['approval_request_id' => $approval->id]);
            } else {
                foreach ($assets as $asset) {
                    $movement = $this->movements->submit($asset, $data, $actor);
                    $movement->update(['kit_assignment_id' => $assignment->id]);
                }
            }

            return $assignment;
        });
    }

    /** single mode: called by ApplyKitAssignment on the terminal ApprovalRequestApproved. */
    public function applyBatch(KitAssignment $assignment): void
    {
        if ($batch = $assignment->batch) {
            $this->movements->applyBulk($batch);
        }

        $assignment->update(['status' => 'completed']);
    }

    /** single mode: called by MarkKitAssignmentRejected — frees the assets for resubmission. */
    public function markRejected(KitAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment) {
            if ($batch = $assignment->batch) {
                $batch->movements()->update(['status' => 'rejected']);
                $batch->update(['status' => 'rejected']);
            }

            $assignment->update(['status' => 'rejected']);
        });
    }
}
