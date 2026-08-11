<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateReportExport;
use App\Models\Asset;
use App\Models\Company;
use App\Models\ExportLog;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Reports\ReportPdfExporter;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_stores_the_file_logs_the_export_and_notifies_the_user(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        Asset::factory()->count(2)->create(['company_id' => $company->id]);
        $user = User::factory()->create();

        (new GenerateReportExport($user->id, 'asset_register', ['company_id' => $company->id]))->handle(
            app(ReportService::class),
            app(ReportPdfExporter::class),
            app(NotificationService::class),
        );

        $log = ExportLog::first();
        $this->assertNotNull($log);
        $this->assertSame('asset_register', $log->report_type);
        $this->assertSame(2, $log->row_count);
        Storage::disk('public')->assertExists($log->file_name);

        $notification = $user->fresh()->notifications->first();
        $this->assertNotNull($notification);
        $this->assertSame('export_ready', $notification->type);
        $this->assertSame(2, $notification->data['row_count']);
        $this->assertSame($log->file_name, $notification->data['file_name']);
        $this->assertNotEmpty($notification->data['download_url']);
    }

    public function test_handle_writes_a_pdf_when_format_is_pdf(): void
    {
        Storage::fake('public');

        Asset::factory()->count(1)->create();
        $user = User::factory()->create();

        (new GenerateReportExport($user->id, 'asset_register', [], 'pdf'))->handle(
            app(ReportService::class),
            app(ReportPdfExporter::class),
            app(NotificationService::class),
        );

        $log = ExportLog::first();
        $this->assertStringEndsWith('.pdf', $log->file_name);
        Storage::disk('public')->assertExists($log->file_name);
    }
}
