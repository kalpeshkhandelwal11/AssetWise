<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CategoryField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AssetShowDynamicFieldsTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_detail_page_shows_saved_custom_field_value(): void
    {
        $category = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'cpu_cores', 'label' => 'CPU Cores', 'field_type' => 'number', 'is_active' => true]);
        $asset = Asset::factory()->create(['category_id' => $category->id]);
        $asset->fieldValues()->create(['category_field_id' => $field->id, 'value_number' => 16]);

        $this->actingAs($this->admin())
             ->get(route('assets.show', $asset))
             ->assertOk()
             ->assertSee('CPU Cores')
             ->assertSee('16');
    }

    public function test_soft_deleted_field_still_renders_read_only_with_archived_marker(): void
    {
        $category = AssetCategory::factory()->create();
        $field = CategoryField::create(['category_id' => $category->id, 'field_key' => 'legacy_field', 'label' => 'Legacy Field', 'field_type' => 'text', 'is_active' => true]);
        $asset = Asset::factory()->create(['category_id' => $category->id]);
        $asset->fieldValues()->create(['category_field_id' => $field->id, 'value_text' => 'archived value']);

        $field->delete();

        $this->actingAs($this->admin())
             ->get(route('assets.show', $asset))
             ->assertOk()
             ->assertSee('Legacy Field')
             ->assertSee('archived value')
             ->assertSee('archived');
    }
}
