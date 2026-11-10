<?php

namespace Tests\Feature\Assets;

use App\Jobs\GenerateAssetExport;
use App\Models\Asset;
use App\Models\AssetMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/** PDF export from the asset export page, "Export selected", and Movement History. */
class PdfExportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_asset_export_page_downloads_a_pdf(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        Asset::factory()->create(['name' => 'PDF-LAPTOP']);

        $this->actingAs($admin)->post(route('assets.export.store'), ['format' => 'pdf'])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_selected_honours_the_pdf_format(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $asset = Asset::factory()->create();

        $this->actingAs($admin)->post(route('assets.export.store'), ['format' => 'pdf', 'asset_ids' => [$asset->id]])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_large_pdf_export_is_queued_with_its_format(): void
    {
        Queue::fake();
        $admin = $this->createUserWithRole('Super Admin');
        // Share one type/status — the factory otherwise mints a unique master per asset.
        $first = Asset::factory()->create();
        Asset::factory()->count(500)->create([
            'asset_type_id' => $first->asset_type_id,
            'status_id'     => $first->status_id,
            'category_id'   => $first->category_id,
            'company_id'    => $first->company_id,
        ]);

        $this->actingAs($admin)->post(route('assets.export.store'), ['format' => 'pdf'])->assertRedirect();

        Queue::assertPushed(GenerateAssetExport::class, fn ($job) => $job->format === 'pdf');
    }

    public function test_movement_history_exports_pdf(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        AssetMovement::factory()->create();

        $this->actingAs($admin)->get(route('movements.export', ['format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)->get(route('movements.export'))
            ->assertOk()
            ->assertDownload();
    }
}
