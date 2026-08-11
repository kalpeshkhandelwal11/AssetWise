<?php

namespace Tests\Feature\Reports;

use App\Jobs\GenerateReportExport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ReportExportQueueingTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    /** Shared lookups so 501 assets don't each mint a unique AssetType/AssetStatus via the factory. */
    private function bulkAssetOverrides(): array
    {
        $company = Company::factory()->create();
        $category = AssetCategory::factory()->create();
        $type = AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);

        return [
            'company_id'    => $company->id,
            'category_id'   => $category->id,
            'asset_type_id' => $type->id,
            'status_id'     => $status->id,
        ];
    }

    public function test_small_result_set_streams_inline_without_queueing(): void
    {
        Queue::fake();
        Excel::fake();

        $admin = $this->createUserWithRole('Super Admin');
        Asset::factory()->count(3)->create();

        $this->actingAs($admin)->post(route('reports.export', 'asset_register'), [])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_large_result_set_is_queued_instead_of_streamed(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('Super Admin');
        Asset::factory()->count(501)->create($this->bulkAssetOverrides());

        $response = $this->actingAs($admin)->post(route('reports.export', 'asset_register'), []);

        Queue::assertPushed(GenerateReportExport::class, fn ($job) => $job->userId === $admin->id
            && $job->reportType === 'asset_register'
            && $job->format === 'xlsx');
        $response->assertRedirect(route('reports.show', 'asset_register'));
    }

    public function test_pdf_format_is_carried_through_to_the_queued_job(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('Super Admin');
        Asset::factory()->count(501)->create($this->bulkAssetOverrides());

        $this->actingAs($admin)->post(route('reports.export', 'asset_register'), ['format' => 'pdf']);

        Queue::assertPushed(GenerateReportExport::class, fn ($job) => $job->format === 'pdf');
    }
}
