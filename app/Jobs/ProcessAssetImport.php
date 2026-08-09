<?php

namespace App\Jobs;

use App\Imports\AssetImport;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Services\AssetService;
use App\Services\DynamicFieldService;
use App\Services\NotificationService;
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

        try {
            Excel::import(new AssetImport($batch, $actor, $assets, $fields), $this->storedPath, 'local');
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

        $notifications->send($actor, 'import_completed', [
            'batch_id'      => $batch->id,
            'success_count' => $successCount,
            'error_count'   => $errorCount,
        ]);
    }
}
