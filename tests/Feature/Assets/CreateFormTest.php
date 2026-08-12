<?php

namespace Tests\Feature\Assets;

use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Tag;
use Database\Seeders\SharedMastersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CreateFormTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    /** @return array{0:Company,1:AssetCategory,2:AssetType,3:AssetStatus} */
    private function refs(): array
    {
        return [
            Company::factory()->create(),
            AssetCategory::factory()->create(),
            AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]),
            AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]),
        ];
    }

    public function test_store_persists_vendor_invoice_and_useful_life(): void
    {
        [$company, $category, $type, $status] = $this->refs();

        $this->actingAs($this->admin())
             ->post(route('assets.store'), [
                 'name'              => 'Laptop',
                 'company_id'        => $company->id,
                 'category_id'       => $category->id,
                 'asset_type_id'     => $type->id,
                 'status_id'         => $status->id,
                 'vendor_invoice_no' => 'INV-2026-001',
                 'useful_life_years' => 5,
                 'eol_projected_date' => '2031-01-01',
             ])
             ->assertSessionHasNoErrors()
             ->assertRedirect();

        $this->assertDatabaseHas('assets', [
            'name'              => 'Laptop',
            'vendor_invoice_no' => 'INV-2026-001',
            'useful_life_years' => 5,
        ]);
    }

    public function test_store_saves_labelled_media_as_attachments(): void
    {
        Storage::fake('public');
        [$company, $category, $type, $status] = $this->refs();

        $this->actingAs($this->admin())
             ->post(route('assets.store'), [
                 'name'          => 'Printer',
                 'company_id'    => $company->id,
                 'category_id'   => $category->id,
                 'asset_type_id' => $type->id,
                 'status_id'     => $status->id,
                 'media'         => [UploadedFile::fake()->image('photo.jpg'), UploadedFile::fake()->create('inv.pdf', 10)],
                 'media_labels'  => ['photo', 'invoice'],
             ])
             ->assertSessionHasNoErrors()
             ->assertRedirect();

        $this->assertDatabaseHas('asset_attachments', ['type' => 'photo']);
        $this->assertDatabaseHas('asset_attachments', ['type' => 'invoice']);
    }

    public function test_store_rejects_unknown_media_label(): void
    {
        Storage::fake('public');
        [$company, $category, $type, $status] = $this->refs();

        $this->actingAs($this->admin())
             ->post(route('assets.store'), [
                 'name'          => 'X',
                 'company_id'    => $company->id,
                 'category_id'   => $category->id,
                 'asset_type_id' => $type->id,
                 'status_id'     => $status->id,
                 'media'         => [UploadedFile::fake()->image('a.jpg')],
                 'media_labels'  => ['bogus'],
             ])
             ->assertSessionHasErrors('media_labels.0');
    }

    public function test_store_assigns_an_available_tag(): void
    {
        [$company, $category, $type, $status] = $this->refs();
        $tag = Tag::factory()->available()->create();

        $this->actingAs($this->admin())
             ->post(route('assets.store'), [
                 'name'          => 'Scanner',
                 'company_id'    => $company->id,
                 'category_id'   => $category->id,
                 'asset_type_id' => $type->id,
                 'status_id'     => $status->id,
                 'tag_id'        => $tag->id,
             ])
             ->assertSessionHasNoErrors()
             ->assertRedirect();

        $this->assertSame('assigned', $tag->fresh()->status);
        $this->assertDatabaseHas('asset_tag_assignments', ['tag_id' => $tag->id, 'status' => 'active']);
    }

    public function test_custodian_can_be_an_employee(): void
    {
        [$company, $category, $type, $status] = $this->refs();
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin())
             ->post(route('assets.store'), [
                 'name'          => 'Desk',
                 'company_id'    => $company->id,
                 'category_id'   => $category->id,
                 'asset_type_id' => $type->id,
                 'status_id'     => $status->id,
                 'custodian_id'  => $employee->id,
             ])
             ->assertSessionHasNoErrors()
             ->assertRedirect();

        $this->assertDatabaseHas('assets', ['name' => 'Desk', 'custodian_id' => $employee->id]);
    }

    public function test_status_renames_are_seeded(): void
    {
        $this->seed(SharedMastersSeeder::class);

        $this->assertDatabaseHas('asset_statuses', ['code' => 'DISPOSED', 'name' => 'Scrapped']);
        $this->assertDatabaseHas('asset_statuses', ['code' => 'ICT', 'name' => 'Sold']);
    }
}
