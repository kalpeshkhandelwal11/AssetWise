<?php

namespace Tests\Feature\Assets;

use App\Jobs\GenerateAssetExport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\CategoryField;
use App\Models\Company;
use App\Models\ExportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('assets.export.index'))->assertForbidden();
    }

    public function test_index_is_accessible_with_assets_export(): void
    {
        $this->actingAs($this->admin())
             ->get(route('assets.export.index'))
             ->assertOk();
    }

    public function test_store_requires_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('assets.export.store'), [])->assertForbidden();
    }

    public function test_store_respects_the_company_filter(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        Asset::factory()->create(['name' => 'Asset A', 'company_id' => $companyA->id]);
        Asset::factory()->create(['name' => 'Asset B', 'company_id' => $companyB->id]);

        $this->actingAs($this->admin())
             ->post(route('assets.export.store'), ['company_id' => $companyA->id])
             ->assertOk();

        Excel::assertDownloaded('/^assets-export-.*\.xlsx$/', function ($export) {
            return $export->rowCount() === 1;
        });
    }

    public function test_store_includes_company_and_resolved_custom_columns(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $company = Company::factory()->create(['code' => 'ACME', 'name' => 'Acme Corp']);
        $category = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'ram_gb', 'label' => 'RAM (GB)', 'field_type' => 'number', 'is_active' => true]);
        $asset = Asset::factory()->create(['company_id' => $company->id, 'category_id' => $category->id]);
        $asset->fieldValues()->create(['category_field_id' => $field->id, 'value_number' => 32]);

        $this->actingAs($this->admin())
             ->post(route('assets.export.store'), [])
             ->assertOk();

        Excel::assertDownloaded('/^assets-export-.*\.xlsx$/', function ($export) {
            $headings = $export->headings();

            return in_array('Company Code', $headings, true)
                && in_array('Company Name', $headings, true)
                && in_array('RAM (GB)', $headings, true);
        });
    }

    public function test_store_logs_export_with_correct_row_count(): void
    {
        Excel::fake();

        $company = Company::factory()->create();
        Asset::factory()->count(3)->create(['company_id' => $company->id]);
        Asset::factory()->count(2)->create();

        $this->actingAs($this->admin())
             ->post(route('assets.export.store'), ['company_id' => $company->id]);

        $log = ExportLog::first();
        $this->assertNotNull($log);
        $this->assertSame('assets', $log->report_type);
        $this->assertSame(3, $log->row_count);
    }

    public function test_small_result_set_streams_inline_without_queueing(): void
    {
        Queue::fake();
        Excel::fake();

        Asset::factory()->count(3)->create();

        $this->actingAs($this->admin())->post(route('assets.export.store'), []);

        Queue::assertNothingPushed();
    }

    public function test_large_result_set_is_queued_instead_of_streamed(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $category = AssetCategory::factory()->create();
        $type = AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
        $admin = $this->admin();

        Asset::factory()->count(501)->create([
            'company_id'    => $company->id,
            'category_id'   => $category->id,
            'asset_type_id' => $type->id,
            'status_id'     => $status->id,
            'created_by'    => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('assets.export.store'), []);

        Queue::assertPushed(GenerateAssetExport::class, fn ($job) => $job->userId === $admin->id);
        $response->assertRedirect(route('assets.export.index'));
    }
}
