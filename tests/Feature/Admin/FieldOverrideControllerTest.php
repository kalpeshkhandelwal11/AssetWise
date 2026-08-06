<?php

namespace Tests\Feature\Admin;

use App\Models\AssetCategory;
use App\Models\CategoryField;
use App\Services\DynamicFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class FieldOverrideControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_store_creates_hide_override_and_reflects_in_resolve(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $field = CategoryField::create(['category_id' => $parent->id, 'field_key' => 'brand', 'label' => 'Brand', 'field_type' => 'text', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->post(route('admin.categories.fields.override.store', [$child, $field]), [
                 'override_type' => 'hide',
             ])
             ->assertRedirect(route('admin.categories.fields.index', $child));

        $this->assertDatabaseHas('category_field_overrides', [
            'category_id' => $child->id, 'category_field_id' => $field->id, 'override_type' => 'hide',
        ]);

        $resolved = app(DynamicFieldService::class)->resolveForCategory($child->id);
        $this->assertCount(0, $resolved);
    }

    public function test_store_creates_relabel_override(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $field = CategoryField::create(['category_id' => $parent->id, 'field_key' => 'brand', 'label' => 'Brand', 'field_type' => 'text', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->post(route('admin.categories.fields.override.store', [$child, $field]), [
                 'override_type'  => 'relabel',
                 'override_label' => 'Manufacturer Brand',
             ]);

        $resolved = app(DynamicFieldService::class)->resolveForCategory($child->id);
        $this->assertSame('Manufacturer Brand', $resolved->first()->label);
    }

    public function test_destroy_removes_override(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $field = CategoryField::create(['category_id' => $parent->id, 'field_key' => 'brand', 'label' => 'Brand', 'field_type' => 'text', 'is_active' => true]);
        $child->fieldOverrides()->create(['category_field_id' => $field->id, 'override_type' => 'hide']);

        $this->actingAs($this->admin())
             ->delete(route('admin.categories.fields.override.destroy', [$child, $field]))
             ->assertRedirect(route('admin.categories.fields.index', $child));

        $this->assertDatabaseMissing('category_field_overrides', ['category_id' => $child->id, 'category_field_id' => $field->id]);
    }

    public function test_rejects_overriding_a_directly_owned_field(): void
    {
        $category = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'brand', 'label' => 'Brand', 'field_type' => 'text', 'is_active' => true]);

        $this->actingAs($this->admin())
             ->post(route('admin.categories.fields.override.store', [$category, $field]), [
                 'override_type' => 'hide',
             ])
             ->assertSessionHasErrors('override_type');
    }
}
