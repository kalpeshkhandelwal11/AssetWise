<?php

namespace Tests\Feature\Assets;

use App\Models\AssetCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.categories.index'))->assertRedirect('/login');
    }

    public function test_index_requires_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = \App\Models\User::factory()->create(); // no role, no permissions

        $this->actingAs($user)
             ->get(route('admin.categories.index'))
             ->assertForbidden();
    }

    public function test_index_shows_category_tree_with_indentation(): void
    {
        $parent = AssetCategory::factory()->create(['name' => 'IT Equipment']);
        AssetCategory::factory()->create(['name' => 'Laptops', 'parent_id' => $parent->id]);

        $this->actingAs($this->admin())
             ->get(route('admin.categories.index'))
             ->assertOk()
             ->assertSee('IT Equipment')
             ->assertSee('Laptops');
    }

    public function test_store_creates_category(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.categories.store'), [
                 'name' => 'Furniture',
                 'code' => 'furniture',
             ])
             ->assertRedirect(route('admin.categories.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseHas('asset_categories', ['code' => 'FURNITURE', 'name' => 'Furniture']);
    }

    public function test_store_creates_child_category(): void
    {
        $parent = AssetCategory::factory()->create();

        $this->actingAs($this->admin())
             ->post(route('admin.categories.store'), [
                 'name' => 'Sub Category',
                 'code' => 'SUBCAT',
                 'parent_id' => $parent->id,
             ]);

        $this->assertDatabaseHas('asset_categories', ['code' => 'SUBCAT', 'parent_id' => $parent->id]);
    }

    public function test_store_requires_unique_code(): void
    {
        AssetCategory::factory()->create(['code' => 'DUP']);

        $this->actingAs($this->admin())
             ->post(route('admin.categories.store'), ['name' => 'Another', 'code' => 'DUP'])
             ->assertSessionHasErrors('code');
    }

    public function test_update_saves_changes(): void
    {
        $category = AssetCategory::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin())
             ->put(route('admin.categories.update', $category), [
                 'name' => 'New Name',
                 'code' => $category->code,
             ])
             ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('asset_categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_destroy_soft_deletes_category(): void
    {
        $category = AssetCategory::factory()->create();

        $this->actingAs($this->admin())
             ->delete(route('admin.categories.destroy', $category))
             ->assertRedirect(route('admin.categories.index'));

        $this->assertSoftDeleted('asset_categories', ['id' => $category->id]);
    }
}
