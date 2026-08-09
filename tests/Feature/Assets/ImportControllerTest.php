<?php

namespace Tests\Feature\Assets;

use App\Jobs\ProcessAssetImport;
use App\Models\AssetCategory;
use App\Models\CategoryField;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ImportControllerTest extends TestCase
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

        $this->actingAs($user)->get(route('assets.import.index'))->assertForbidden();
    }

    public function test_index_is_accessible_with_imports_manage(): void
    {
        $this->actingAs($this->admin())
             ->get(route('assets.import.index'))
             ->assertOk();
    }

    public function test_template_download_requires_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $category = AssetCategory::factory()->create();

        $this->actingAs($user)
             ->get(route('assets.import.template', $category))
             ->assertForbidden();
    }

    public function test_template_matches_resolved_field_schema_including_inherited_fields(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        CategoryField::create(['category_id' => $parent->id, 'field_key' => 'inherited_field', 'label' => 'Inherited', 'field_type' => 'text', 'is_active' => true]);
        CategoryField::create(['category_id' => $child->id, 'field_key' => 'own_field', 'label' => 'Own', 'field_type' => 'text', 'is_active' => true]);

        Excel::fake();

        $this->actingAs($this->admin())
             ->get(route('assets.import.template', $child))
             ->assertOk();

        Excel::assertDownloaded('asset-import-template-'.$child->code.'.xlsx', function ($export) {
            $headings = $export->headings();

            return in_array('inherited_field', $headings, true)
                && in_array('own_field', $headings, true)
                && in_array('company_code', $headings, true)
                && in_array('name', $headings, true);
        });
    }

    public function test_store_requires_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->givePermissionTo('imports.manage'); // missing assets.bulk
        $category = AssetCategory::factory()->create();

        $file = UploadedFile::fake()->create('assets.xlsx', 10);

        $this->actingAs($user)
             ->post(route('assets.import.store'), [
                 'category_id' => $category->id,
                 'file'        => $file,
             ])
             ->assertForbidden();
    }

    public function test_store_dispatches_process_asset_import_job_with_the_new_batch_id(): void
    {
        Queue::fake();
        Storage::fake('local');

        $category = AssetCategory::factory()->create();
        $admin = $this->admin();

        $file = UploadedFile::fake()->create('assets.xlsx', 10);

        $response = $this->actingAs($admin)
             ->post(route('assets.import.store'), [
                 'category_id' => $category->id,
                 'file'        => $file,
             ]);

        $batch = ImportBatch::first();
        $this->assertNotNull($batch);
        $this->assertSame($category->id, $batch->category_id);
        $this->assertSame($admin->id, $batch->user_id);
        $this->assertSame('processing', $batch->status);

        $response->assertRedirect(route('assets.import.show', $batch));

        Queue::assertPushed(ProcessAssetImport::class, fn ($job) => $job->batchId === $batch->id);
    }

    public function test_show_requires_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $category = AssetCategory::factory()->create();
        $batch = ImportBatch::create([
            'category_id' => $category->id,
            'user_id'     => $this->admin()->id,
            'filename'    => 'x.xlsx',
            'status'      => 'completed',
        ]);

        $this->actingAs($user)
             ->get(route('assets.import.show', $batch))
             ->assertForbidden();
    }

    public function test_show_displays_batch_report(): void
    {
        $category = AssetCategory::factory()->create();
        $admin = $this->admin();
        $batch = ImportBatch::create([
            'category_id'   => $category->id,
            'user_id'       => $admin->id,
            'filename'      => 'my-upload.xlsx',
            'status'        => 'completed',
            'total_rows'    => 2,
            'success_count' => 1,
            'error_count'   => 1,
        ]);
        $batch->rows()->create(['row_number' => 2, 'status' => 'success']);
        $batch->rows()->create(['row_number' => 3, 'status' => 'failed', 'errors' => ['company_code' => ['No active company found with code "X".']]]);

        $this->actingAs($admin)
             ->get(route('assets.import.show', $batch))
             ->assertOk()
             ->assertSee('my-upload.xlsx')
             ->assertSee('No active company found with code "X".');
    }
}
