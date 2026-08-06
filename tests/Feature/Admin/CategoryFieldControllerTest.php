<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CategoryField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CategoryFieldControllerTest extends TestCase
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
        $category = AssetCategory::factory()->create();

        $this->actingAs($user)
             ->get(route('admin.categories.fields.index', $category))
             ->assertForbidden();
    }

    public function test_index_shows_own_and_inherited_fields(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        CategoryField::create(['category_id' => $parent->id, 'field_key' => 'inherited_one', 'label' => 'Inherited One', 'field_type' => 'text', 'is_active' => true]);
        CategoryField::create(['category_id' => $child->id, 'field_key' => 'own_one', 'label' => 'Own One', 'field_type' => 'text', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->get(route('admin.categories.fields.index', $child))
             ->assertOk()
             ->assertSee('Own One')
             ->assertSee('Inherited One');
    }

    public function test_store_creates_field_with_dropdown_options(): void
    {
        $category = AssetCategory::factory()->create();

        $this->actingAs($this->admin())
             ->post(route('admin.categories.fields.store', $category), [
                 'field_key'  => 'os',
                 'label'      => 'Operating System',
                 'field_type' => 'dropdown',
                 'options'    => [
                     ['value' => 'linux', 'label' => 'Linux'],
                     ['value' => 'windows', 'label' => 'Windows'],
                 ],
             ])
             ->assertRedirect(route('admin.categories.fields.index', $category));

        $this->assertDatabaseHas('category_fields', ['category_id' => $category->id, 'field_key' => 'os', 'field_type' => 'dropdown']);
        $this->assertDatabaseHas('category_field_options', ['option_value' => 'linux']);
        $this->assertDatabaseHas('category_field_options', ['option_value' => 'windows']);
    }

    public function test_store_rejects_key_colliding_with_ancestor_field(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        CategoryField::create(['category_id' => $parent->id, 'field_key' => 'ram_gb', 'label' => 'RAM', 'field_type' => 'number', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->post(route('admin.categories.fields.store', $child), [
                 'field_key'  => 'ram_gb',
                 'label'      => 'RAM Again',
                 'field_type' => 'number',
             ])
             ->assertSessionHasErrors('field_key');

        $this->assertDatabaseMissing('category_fields', ['category_id' => $child->id, 'field_key' => 'ram_gb']);
    }

    public function test_store_rejects_key_colliding_with_descendant_field(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        CategoryField::create(['category_id' => $child->id, 'field_key' => 'ram_gb', 'label' => 'RAM', 'field_type' => 'number', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->post(route('admin.categories.fields.store', $parent), [
                 'field_key'  => 'ram_gb',
                 'label'      => 'RAM Again',
                 'field_type' => 'number',
             ])
             ->assertSessionHasErrors('field_key');
    }

    public function test_update_blocks_field_type_change_once_values_exist(): void
    {
        $category = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'notes_f', 'label' => 'Notes', 'field_type' => 'text', 'is_active' => true]);
        $asset = Asset::factory()->create(['category_id' => $category->id]);
        $asset->fieldValues()->create(['category_field_id' => $field->id, 'value_text' => 'hello']);

        $this->actingAs($this->admin())
             ->put(route('admin.categories.fields.update', [$category, $field]), [
                 'field_key'  => 'notes_f',
                 'label'      => 'Notes',
                 'field_type' => 'number',
             ])
             ->assertSessionHasErrors('field_type');

        $this->assertDatabaseHas('category_fields', ['id' => $field->id, 'field_type' => 'text']);
    }

    public function test_update_away_from_dropdown_deletes_options(): void
    {
        $category = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'os', 'label' => 'OS', 'field_type' => 'dropdown', 'is_active' => true]);
        $field->options()->create(['option_value' => 'linux', 'option_label' => 'Linux', 'sort_order' => 0, 'is_active' => true]);

        $this->actingAs($this->admin())
             ->put(route('admin.categories.fields.update', [$category, $field]), [
                 'field_key'  => 'os',
                 'label'      => 'OS',
                 'field_type' => 'text',
             ])
             ->assertRedirect(route('admin.categories.fields.index', $category));

        $this->assertDatabaseMissing('category_field_options', ['category_field_id' => $field->id]);
    }

    public function test_destroy_soft_deletes_field(): void
    {
        $category = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'notes_f', 'label' => 'Notes', 'field_type' => 'text', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->delete(route('admin.categories.fields.destroy', [$category, $field]))
             ->assertRedirect(route('admin.categories.fields.index', $category));

        $this->assertSoftDeleted('category_fields', ['id' => $field->id]);
    }
}
