<?php

namespace App\Listeners;

use App\Events\ApprovalRequestApproved;
use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\ImportBatch;
use App\Support\CompanyVisibility;
use Illuminate\Support\Facades\DB;
use App\Services\DepreciationService;

/**
 * Terminal-approval of a new asset's creation request: promote it out of DRAFT and live.
 *
 * Auto-discovered via the typed handle() parameter — never also register this in
 * AppServiceProvider::boot() (see CLAUDE.md's listener note).
 */
class ApplyAssetCreation
{
    public function __construct(private readonly DepreciationService $depreciation)
    {
    }

    public function handle(ApprovalRequestApproved $event): void
    {
        if ($event->request->workflow->module !== 'asset_creation') {
            return;
        }

        $asset = $event->request->approvable;

        if ($event->request->approvable instanceof ImportBatch) {
            $this->promoteImportBatch($event->request->approvable);

            return;
        }

        if ($asset instanceof Asset) {
            // Promote to Assigned when it already has a custodian, otherwise Available.
            $live = AssetStatus::where('code', $asset->custodian_id ? 'ASSIGNED' : 'AVAILABLE')->first()
                ?? AssetStatus::where('code', 'AVAILABLE')->first();
            if ($live) {
                $asset->update(['status_id' => $live->id]);
            }

            // Now that the asset is live, auto-create its depreciation from the category
            // default (no-op if no default / no cost). Runs after promotion so the schedule
            // reflects the live asset, not a draft that could have been rejected.
            $this->depreciation->applyCategoryDefault($asset->fresh());
        }
    }

    /**
     * One approval covers the whole import: every row asset still in DRAFT goes live with the
     * status its spreadsheet row asked for, then gets its category depreciation default.
     */
    private function promoteImportBatch(ImportBatch $batch): void
    {
        CompanyVisibility::unrestricted(function () use ($batch) {
            DB::transaction(function () use ($batch) {
                $draftId = AssetStatus::where('code', 'DRAFT')->value('id');
                $availableId = AssetStatus::where('code', 'AVAILABLE')->value('id');

                $batch->rows()->where('status', 'success')->whereNotNull('asset_id')->get()
                    ->each(function ($row) use ($draftId, $availableId) {
                        $asset = Asset::find($row->asset_id);

                        if (! $asset || (int) $asset->status_id !== (int) $draftId) {
                            return; // deleted or already handled individually
                        }

                        $asset->update(['status_id' => $row->intended_status_id ?? $availableId]);
                        $this->depreciation->applyCategoryDefault($asset->fresh());
                    });

                $batch->update(['approval_status' => 'approved']);
            });
        });
    }
}
