<?php

namespace Tests\Feature\Api;

use App\Models\AssetCategory;
use App\Models\CategoryField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFieldControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth(): void
    {
        $category = AssetCategory::factory()->create();

        $this->getJson("/api/categories/{$category->id}/fields")->assertUnauthorized();
    }

    public function test_returns_resolved_fields_with_overrides_applied(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $field = CategoryField::create(['category_id' => $parent->id, 'field_key' => 'brand', 'label' => 'Brand', 'field_type' => 'text', 'is_active' => true]);
        $child->fieldOverrides()->create(['category_field_id' => $field->id, 'override_type' => 'relabel', 'override_label' => 'Manufacturer']);

        $response = $this->actingAs(User::factory()->create())
             ->getJson("/api/categories/{$child->id}/fields")
             ->assertOk();

        $response->assertJsonFragment(['field_key' => 'brand', 'label' => 'Manufacturer']);
    }

    public function test_payload_never_includes_a_value_key(): void
    {
        $category = AssetCategory::factory()->create();
        CategoryField::create(['category_id' => $category->id, 'field_key' => 'brand', 'label' => 'Brand', 'field_type' => 'text', 'is_active' => true]);

        $response = $this->actingAs(User::factory()->create())
             ->getJson("/api/categories/{$category->id}/fields")
             ->assertOk();

        $response->assertJsonMissingPath('0.value');
    }
}
