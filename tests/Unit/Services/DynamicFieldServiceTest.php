<?php

namespace Tests\Unit\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CategoryField;
use App\Models\CategoryFieldOption;
use App\Models\CategoryFieldOverride;
use App\Services\DynamicFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DynamicFieldServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DynamicFieldService
    {
        return new DynamicFieldService();
    }

    private function field(AssetCategory $category, array $overrides = []): CategoryField
    {
        return CategoryField::create(array_merge([
            'category_id'   => $category->id,
            'field_key'     => 'field_' . uniqid(),
            'label'         => 'Test Field',
            'field_type'    => 'text',
            'is_required'   => false,
            'display_order' => 0,
            'is_searchable' => false,
            'is_active'     => true,
        ], $overrides));
    }

    public function test_resolves_own_fields_for_category_with_no_parent(): void
    {
        $category = AssetCategory::factory()->create();
        $this->field($category, ['field_key' => 'ram_gb', 'label' => 'RAM (GB)']);

        $resolved = $this->service()->resolveForCategory($category->id);

        $this->assertCount(1, $resolved);
        $this->assertSame('ram_gb', $resolved->first()->fieldKey);
        $this->assertFalse($resolved->first()->isInherited);
    }

    public function test_inherits_fields_from_ancestors(): void
    {
        $grandparent = AssetCategory::factory()->create();
        $parent = AssetCategory::factory()->create(['parent_id' => $grandparent->id]);
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $this->field($grandparent, ['field_key' => 'brand']);

        $resolved = $this->service()->resolveForCategory($child->id);

        $this->assertCount(1, $resolved);
        $this->assertSame('brand', $resolved->first()->fieldKey);
        $this->assertTrue($resolved->first()->isInherited);
    }

    public function test_hide_override_removes_field_for_that_category_only(): void
    {
        $parent = AssetCategory::factory()->create();
        $childA = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $childB = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $field = $this->field($parent, ['field_key' => 'warranty_months']);

        CategoryFieldOverride::create([
            'category_id'       => $childA->id,
            'category_field_id' => $field->id,
            'override_type'     => 'hide',
        ]);

        $resolvedA = $this->service()->resolveForCategory($childA->id);
        $resolvedB = $this->service()->resolveForCategory($childB->id);

        $this->assertCount(0, $resolvedA);
        $this->assertCount(1, $resolvedB);
    }

    public function test_relabel_override_changes_label(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $field = $this->field($parent, ['field_key' => 'notes_field', 'label' => 'Original Label']);

        CategoryFieldOverride::create([
            'category_id'       => $child->id,
            'category_field_id' => $field->id,
            'override_type'     => 'relabel',
            'override_label'    => 'New Label',
        ]);

        $resolved = $this->service()->resolveForCategory($child->id);

        $this->assertSame('New Label', $resolved->first()->label);
    }

    public function test_change_required_override_changes_required(): void
    {
        $parent = AssetCategory::factory()->create();
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $field = $this->field($parent, ['field_key' => 'notes_field', 'is_required' => false]);

        CategoryFieldOverride::create([
            'category_id'       => $child->id,
            'category_field_id' => $field->id,
            'override_type'     => 'change_required',
            'is_required'       => true,
        ]);

        $resolved = $this->service()->resolveForCategory($child->id);

        $this->assertTrue($resolved->first()->isRequired);
    }

    public function test_override_recorded_on_intermediate_ancestor_is_ignored(): void
    {
        $grandparent = AssetCategory::factory()->create();
        $parent = AssetCategory::factory()->create(['parent_id' => $grandparent->id]);
        $child = AssetCategory::factory()->create(['parent_id' => $parent->id]);
        $field = $this->field($grandparent, ['field_key' => 'brand']);

        // Recorded on the intermediate ancestor ($parent), not the leaf ($child) being resolved.
        CategoryFieldOverride::create([
            'category_id'       => $parent->id,
            'category_field_id' => $field->id,
            'override_type'     => 'hide',
        ]);

        $resolved = $this->service()->resolveForCategory($child->id);

        $this->assertCount(1, $resolved);
    }

    public function test_excludes_soft_deleted_and_inactive_fields(): void
    {
        $category = AssetCategory::factory()->create();
        $this->field($category, ['field_key' => 'active_field']);
        $this->field($category, ['field_key' => 'inactive_field', 'is_active' => false]);
        $deleted = $this->field($category, ['field_key' => 'deleted_field']);
        $deleted->delete();

        $resolved = $this->service()->resolveForCategory($category->id);

        $this->assertCount(1, $resolved);
        $this->assertSame('active_field', $resolved->first()->fieldKey);
    }

    public function test_resolve_is_memoized_per_instance(): void
    {
        $category = AssetCategory::factory()->create();
        $this->field($category);
        $service = $this->service();

        $first = $service->resolveForCategory($category->id);
        $second = $service->resolveForCategory($category->id);

        $this->assertSame($first, $second);
    }

    public function test_validate_required_field_missing_fails(): void
    {
        $category = AssetCategory::factory()->create();
        $this->field($category, ['field_key' => 'serial', 'is_required' => true]);
        $resolved = $this->service()->resolveForCategory($category->id);

        $this->expectException(ValidationException::class);
        $this->service()->validate([], $resolved);
    }

    public function test_validate_number_out_of_range_fails(): void
    {
        $category = AssetCategory::factory()->create();
        $this->field($category, [
            'field_key'        => 'cpu_cores',
            'field_type'       => 'number',
            'validation_rules' => ['min' => 1, 'max' => 64],
        ]);
        $resolved = $this->service()->resolveForCategory($category->id);

        $this->expectException(ValidationException::class);
        $this->service()->validate(['cpu_cores' => 128], $resolved);
    }

    public function test_validate_dropdown_rejects_value_not_in_options(): void
    {
        $category = AssetCategory::factory()->create();
        $field = $this->field($category, ['field_key' => 'os', 'field_type' => 'dropdown']);
        CategoryFieldOption::create(['category_field_id' => $field->id, 'option_value' => 'linux', 'option_label' => 'Linux', 'sort_order' => 0, 'is_active' => true]);
        $resolved = $this->service()->resolveForCategory($category->id);

        $this->expectException(ValidationException::class);
        $this->service()->validate(['os' => 'windows'], $resolved);
    }

    public function test_validate_regex_enforced(): void
    {
        $category = AssetCategory::factory()->create();
        $this->field($category, [
            'field_key'        => 'asset_code',
            'field_type'       => 'text',
            'validation_rules' => ['regex' => '^AB-[0-9]+$'],
        ]);
        $resolved = $this->service()->resolveForCategory($category->id);

        $this->expectException(ValidationException::class);
        $this->service()->validate(['asset_code' => 'ZZ-1'], $resolved);
    }

    public function test_save_values_writes_correct_typed_column_per_field_type(): void
    {
        $category = AssetCategory::factory()->create();
        $textField = $this->field($category, ['field_key' => 'notes_f', 'field_type' => 'text']);
        $numberField = $this->field($category, ['field_key' => 'cores', 'field_type' => 'number']);
        $dateField = $this->field($category, ['field_key' => 'exp', 'field_type' => 'date']);
        $boolField = $this->field($category, ['field_key' => 'flag', 'field_type' => 'boolean']);
        $asset = Asset::factory()->create(['category_id' => $category->id]);
        $resolved = $this->service()->resolveForCategory($category->id);

        $this->service()->saveValues($asset, [
            'notes_f' => 'hello',
            'cores'   => '8',
            'exp'     => '2027-01-01',
            'flag'    => '1',
        ], $resolved);

        $this->assertDatabaseHas('asset_field_values', ['asset_id' => $asset->id, 'category_field_id' => $textField->id, 'value_text' => 'hello']);
        $this->assertDatabaseHas('asset_field_values', ['asset_id' => $asset->id, 'category_field_id' => $numberField->id, 'value_number' => 8]);
        $this->assertDatabaseHas('asset_field_values', ['asset_id' => $asset->id, 'category_field_id' => $boolField->id, 'value_boolean' => true]);

        $dateValue = \App\Models\AssetFieldValue::where('asset_id', $asset->id)->where('category_field_id', $dateField->id)->first();
        $this->assertNotNull($dateValue);
        $this->assertSame('2027-01-01', $dateValue->value_date->format('Y-m-d'));
    }

    public function test_save_values_deletes_row_when_optional_value_cleared(): void
    {
        $category = AssetCategory::factory()->create();
        $field = $this->field($category, ['field_key' => 'notes_f', 'field_type' => 'text']);
        $asset = Asset::factory()->create(['category_id' => $category->id]);
        $resolved = $this->service()->resolveForCategory($category->id);

        $this->service()->saveValues($asset, ['notes_f' => 'hello'], $resolved);
        $this->assertDatabaseHas('asset_field_values', ['asset_id' => $asset->id, 'category_field_id' => $field->id]);

        $this->service()->saveValues($asset, ['notes_f' => ''], $resolved);
        $this->assertDatabaseMissing('asset_field_values', ['asset_id' => $asset->id, 'category_field_id' => $field->id]);
    }

    public function test_save_values_never_writes_a_field_absent_from_resolved_set(): void
    {
        $category = AssetCategory::factory()->create();
        $field = $this->field($category, ['field_key' => 'secret']);
        $asset = Asset::factory()->create(['category_id' => $category->id]);

        // Resolved set is empty (simulates the field being hidden by an override) even though
        // the raw input still contains a value for it.
        $this->service()->saveValues($asset, ['secret' => 'value'], collect());

        $this->assertDatabaseMissing('asset_field_values', ['asset_id' => $asset->id, 'category_field_id' => $field->id]);
    }
}
