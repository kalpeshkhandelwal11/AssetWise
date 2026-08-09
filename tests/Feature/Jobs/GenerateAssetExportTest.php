<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateAssetExport;
use App\Models\Asset;
use App\Models\Company;
use App\Models\ExportLog;
use App\Models\User;
use App\Services\DynamicFieldService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateAssetExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_stores_the_file_logs_the_export_and_notifies_the_user(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        Asset::factory()->count(2)->create(['company_id' => $company->id]);
        $user = User::factory()->create();

        (new GenerateAssetExport($user->id, ['company_id' => $company->id]))->handle(
            app(DynamicFieldService::class),
            app(NotificationService::class),
        );

        $log = ExportLog::first();
        $this->assertNotNull($log);
        $this->assertSame('assets', $log->report_type);
        $this->assertSame(2, $log->row_count);
        Storage::disk('public')->assertExists($log->file_name);

        $notification = $user->fresh()->notifications->first();
        $this->assertNotNull($notification);
        $this->assertSame('export_ready', $notification->type);
        $this->assertSame(2, $notification->data['row_count']);
        $this->assertSame($log->file_name, $notification->data['file_name']);
        $this->assertNotEmpty($notification->data['download_url']);
    }
}
