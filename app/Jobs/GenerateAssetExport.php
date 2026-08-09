<?php

namespace App\Jobs;

use App\Exports\AssetExport;
use App\Models\ExportLog;
use App\Models\User;
use App\Services\DynamicFieldService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Heavy exports (row count above the controller's inline threshold) run here instead of
 * blocking the request. The file is written to the public disk (already symlinked via
 * `storage:link`) so the notification's download_url is a plain, working URL with no
 * extra authenticated download route needed.
 */
class GenerateAssetExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $filters,
    ) {
    }

    public function handle(DynamicFieldService $fields, NotificationService $notifications): void
    {
        $user = User::findOrFail($this->userId);

        $export = new AssetExport($this->filters, $fields);
        $fileName = 'exports/assets-export-'.now()->format('Ymd-His').'-'.Str::random(6).'.xlsx';

        Excel::store($export, $fileName, 'public');

        ExportLog::record('assets', $this->filters, $export->rowCount(), $fileName, $user->id);

        $notifications->send($user, 'export_ready', [
            'download_url' => Storage::disk('public')->url($fileName),
            'file_name'    => $fileName,
            'row_count'    => $export->rowCount(),
        ]);
    }
}
