<?php

namespace App\Jobs;

use App\Support\CompanyVisibility;
use App\Imports\AssetImport;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Services\AssetService;
use App\Services\DynamicFieldService;
use App\Services\DepreciationService;
use App\Services\NotificationService;
use App\Services\WorkflowService;
use Illuminate\Validation\ValidationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * First ShouldQueue job in the codebase. Runs the AssetImport reader over the file the
 * controller already stored, then rolls the per-row import_batch_rows results up into
 * the batch's counters and notifies the uploader via NotificationService (M12 stub).
 */
class ProcessAssetImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $batchId,
        public readonly string $storedPath,
    ) {
    }

    public function handle(AssetService $assets, DynamicFieldService $fields, NotificationService $notifications): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);
        $actor = $batch->user;

        // No session in a queued job — evaluate company visibility as the uploader.
        CompanyVisibility::actingAs($actor, fn () => $this->run($batch, $actor, $assets, $fields, $notifications));
    }

    private function run(ImportBatch $batch, \App\Models\User $actor, AssetService $assets, DynamicFieldService $fields, NotificationService $notifications): void
    {
        // Decided once for the whole file: with an active asset_creation workflow every row
        // becomes a DRAFT and the batch goes for ONE approval (not one per asset).
        $draftStatusId = $this->draftStatusId();

        try {
            Excel::import(new AssetImport($batch, $actor, $assets, $fields, $draftStatusId), $this->storedPath, 'local');
        } catch (Throwable $e) {
            Log::error('ProcessAssetImport failed to read the uploaded file.', [
                'batch_id' => $batch->id,
                'message'  => $e->getMessage(),
            ]);

            $batch->update(['status' => 'failed']);
            $notifications->send($actor, 'import_completed', [
                'batch_id' => $batch->id,
                'error'    => 'The uploaded file could not be read.',
            ]);

            return;
        }

        $successCount = ImportBatchRow::where('batch_id', $batch->id)->where('status', 'success')->count();
        $errorCount = ImportBatchRow::where('batch_id', $batch->id)->where('status', 'failed')->count();

        $batch->update([
            'total_rows'    => $successCount + $errorCount,
            'success_count' => $successCount,
            'error_count'   => $errorCount,
            'status'        => 'completed',
        ]);

        $payload = [
            'batch_id'      => $batch->id,
            'success_count' => $successCount,
            'error_count'   => $errorCount,
        ];

        if ($draftStatusId && $successCount > 0) {
            try {
                $request = app(WorkflowService::class)->submit($batch, 'asset_creation', $actor);
                $batch->update(['approval_request_id' => $request->id, 'approval_status' => 'pending']);
                $payload['approval'] = 'submitted';
            } catch (ValidationException $e) {
                // e.g. the workflow was deactivated mid-import: the drafts stay put and can be
                // resubmitted from the import screen.
                $payload['error'] = collect($e->errors())->flatten()->first();
            }
        } elseif ($successCount > 0) {
            // Live import: build each new asset's depreciation from its category default, the
            // same as single-asset creation does.
            $depreciation = app(DepreciationService::class);

            ImportBatchRow::where('batch_id', $batch->id)->where('status', 'success')->whereNotNull('asset_id')
                ->pluck('asset_id')
                ->each(function (int $assetId) use ($depreciation) {
                    if ($asset = Asset::find($assetId)) {
                        $depreciation->applyCategoryDefault($asset);
                        app(\App\Services\OpeningAssignmentService::class)->record($asset->fresh());
                    }
                });
        }

        $notifications->send($actor, 'import_completed', $payload);
    }

    private function draftStatusId(): ?int
    {
        $active = ApprovalWorkflow::where('module', 'asset_creation')->where('is_active', true)->exists();

        return $active ? AssetStatus::where('code', 'DRAFT')->value('id') : null;
    }
}
