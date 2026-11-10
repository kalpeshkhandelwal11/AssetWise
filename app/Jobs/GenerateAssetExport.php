<?php

namespace App\Jobs;

use App\Support\CompanyVisibility;
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
        public readonly string $format = 'xlsx',
    ) {
    }

    public function handle(DynamicFieldService $fields, NotificationService $notifications): void
    {
        $user = User::findOrFail($this->userId);

        // No session in a queued job — evaluate company visibility as the requester, or the
        // export would contain every company's assets.
        CompanyVisibility::actingAs($user, fn () => $this->run($user, $fields, $notifications));
    }

    private function run(User $user, DynamicFieldService $fields, NotificationService $notifications): void
    {
        $fileName = 'exports/assets-export-'.now()->format('Ymd-His').'-'.Str::random(6).".{$this->format}";

        if ($this->format === 'pdf') {
            $export = new \App\Exports\AssetPdfExport($this->filters, $fields);
            app(\App\Services\Reports\ReportPdfExporter::class)->render('Asset Export', $export)->save($fileName, 'public');
        } else {
            $export = new AssetExport($this->filters, $fields);
            Excel::store($export, $fileName, 'public');
        }

        ExportLog::record('assets', $this->filters, $export->rowCount(), $fileName, $user->id);

        $notifications->send($user, 'export_ready', [
            'download_url' => Storage::disk('public')->url($fileName),
            'file_name'    => $fileName,
            'row_count'    => $export->rowCount(),
        ]);
    }
}
