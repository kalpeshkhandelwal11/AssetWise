<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\CategoryField;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AssetDynamicFieldsTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    private function coreAssetPayload(AssetCategory $category, Company $company): array
    {
        $type = AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);

        return [
            'name'          => 'Test Asset',
            'company_id'    => $company->id,
            'category_id'   => $category->id,
            'asset_type_id' => $type->id,
            'status_id'     => $status->id,
        ];
    }

    public function test_combined_validation_errors_appear_in_one_round_trip(): void
    {
        $category = AssetCategory::factory()->create();
        CategoryField::create(['category_id' => $category->id, 'field_key' => 'cpu_cores', 'label' => 'CPU Cores', 'field_type' => 'number', 'is_required' => true, 'is_active' => true]);
        $company = Company::factory()->create();

        $payload = $this->coreAssetPayload($category, $company);
        unset($payload['name']); // core-field error
        // 'fields' left empty entirely -> dynamic-field required error

        $this->actingAs($this->admin())
             ->post(route('assets.store'), $payload)
             ->assertSessionHasErrors(['name', 'fields.cpu_cores']);
    }

    public function test_valid_dynamic_input_round_trips_through_typed_column(): void
    {
        $category = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'cpu_cores', 'label' => 'CPU Cores', 'field_type' => 'number', 'is_active' => true]);
        $company = Company::factory()->create();

        $payload = $this->coreAssetPayload($category, $company);
        $payload['fields'] = ['cpu_cores' => 8];

        $this->actingAs($this->admin())
             ->post(route('assets.store'), $payload)
             ->assertSessionHasNoErrors();

        $asset = Asset::where('name', 'Test Asset')->firstOrFail();
        $this->assertDatabaseHas('asset_field_values', [
            'asset_id' => $asset->id, 'category_field_id' => $field->id, 'value_number' => 8,
        ]);
    }

    public function test_category_change_is_rejected_without_override_permission_once_values_exist(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create();
        $user->givePermissionTo(['assets.view', 'assets.edit', 'assets.create']);

        $category = AssetCategory::factory()->create();
        $otherCategory = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'notes_f', 'label' => 'Notes', 'field_type' => 'text', 'is_active' => true]);
        $asset = Asset::factory()->create(['category_id' => $category->id]);
        $asset->fieldValues()->create(['category_field_id' => $field->id, 'value_text' => 'hello']);

        $this->actingAs($user)
             ->put(route('assets.update', $asset), [
                 'name'          => $asset->name,
                 'category_id'   => $otherCategory->id,
                 'asset_type_id' => $asset->asset_type_id,
                 'status_id'     => $asset->status_id,
             ])
             ->assertRedirect(route('assets.show', $asset));

        $this->assertSame($category->id, $asset->refresh()->category_id);
        $this->assertDatabaseHas('asset_field_values', ['asset_id' => $asset->id, 'category_field_id' => $field->id]);
    }

    public function test_category_change_succeeds_with_override_permission_and_preserves_old_values(): void
    {
        $admin = $this->admin(); // Super Admin has assets.override_category

        $category = AssetCategory::factory()->create();
        $otherCategory = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'notes_f', 'label' => 'Notes', 'field_type' => 'text', 'is_active' => true]);
        $asset = Asset::factory()->create(['category_id' => $category->id]);
        $asset->fieldValues()->create(['category_field_id' => $field->id, 'value_text' => 'hello']);

        $this->actingAs($admin)
             ->put(route('assets.update', $asset), [
                 'name'          => $asset->name,
                 'category_id'   => $otherCategory->id,
                 'asset_type_id' => $asset->asset_type_id,
                 'status_id'     => $asset->status_id,
             ])
             ->assertRedirect(route('assets.show', $asset));

        $this->assertSame($otherCategory->id, $asset->refresh()->category_id);
        // Old EAV row is untouched, not migrated or deleted.
        $this->assertDatabaseHas('asset_field_values', ['asset_id' => $asset->id, 'category_field_id' => $field->id, 'value_text' => 'hello']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Asset::class,
            'subject_id'   => $asset->id,
            'event'        => 'updated',
        ]);
    }

    public function test_inherited_field_reflows_onto_the_re_rendered_create_form_after_a_validation_error(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $unrelated = AssetCategory::factory()->create();
        CategoryField::create(['category_id' => $parent->id, 'field_key' => 'brand', 'label' => 'Brand Name', 'field_type' => 'text', 'is_active' => true]);
        $company = Company::factory()->create();

        $admin = $this->admin();
        $payload = $this->coreAssetPayload($child, $company);
        unset($payload['name']); // force a validation failure so category_id is flashed back via old()

        $this->actingAs($admin)
             ->from(route('assets.create'))
             ->post(route('assets.store'), $payload)
             ->assertSessionHasErrors('name');

        // The re-rendered create form resolves fields for the flashed old('category_id') = $child.
        $this->get(route('assets.create'))
             ->assertOk()
             ->assertSee('Brand Name');

        // A category unrelated to the inheritance chain never sees it (sanity check on resolveForCategory scoping).
        $unrelatedFields = app(\App\Services\DynamicFieldService::class)->resolveForCategory($unrelated->id);
        $this->assertCount(0, $unrelatedFields);
    }
}
