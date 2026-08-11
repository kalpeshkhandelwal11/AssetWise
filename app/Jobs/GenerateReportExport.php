<?php

namespace App\Jobs;

use App\Models\ExportLog;
use App\Models\User;
use App\Services\Reports\ReportPdfExporter;
use App\Services\Reports\ReportRegistry;
use App\Services\ReportService;
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
 * Heavy report exports (row count above the controller's inline threshold) run here
 * instead of blocking the request — the same pattern as M06's GenerateAssetExport,
 * generalized across every M14 report type. The file is written to the public disk
 * (already symlinked via `storage:link`) so the notification's download_url is a
 * plain, working URL with no extra authenticated download route needed.
 */
class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $reportType,
        public readonly array $filters,
        public readonly string $format = 'xlsx',
    ) {
    }

    public function handle(ReportService $reports, ReportPdfExporter $pdfExporter, NotificationService $notifications): void
    {
        $user = User::findOrFail($this->userId);
        $export = $reports->exportFor($this->reportType, $this->filters);
        $rowCount = $export->rowCount();

        $fileName = "exports/{$this->reportType}-report-".now()->format('Ymd-His').'-'.Str::random(6).".{$this->format}";

        if ($this->format === 'pdf') {
            $pdfExporter->render(ReportRegistry::label($this->reportType), $export)->save($fileName, 'public');
        } else {
            Excel::store($export, $fileName, 'public');
        }

        ExportLog::record($this->reportType, $this->filters, $rowCount, $fileName, $user->id);

        $notifications->send($user, 'export_ready', [
            'download_url' => Storage::disk('public')->url($fileName),
            'file_name'    => $fileName,
            'row_count'    => $rowCount,
        ]);
    }
}
