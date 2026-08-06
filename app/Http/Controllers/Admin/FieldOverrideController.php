<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\CategoryField;
use App\Models\CategoryFieldOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FieldOverrideController extends Controller
{
    public function store(Request $request, AssetCategory $category, CategoryField $field): RedirectResponse
    {
        $this->authorize('category_fields.manage');
        $this->assertOverridable($category, $field);

        $data = $request->validate([
            'override_type'  => 'required|in:hide,relabel,change_required',
            'override_label' => 'required_if:override_type,relabel|nullable|string|max:255',
            'is_required'    => 'required_if:override_type,change_required|nullable|boolean',
        ]);

        CategoryFieldOverride::updateOrCreate(
            ['category_id' => $category->id, 'category_field_id' => $field->id],
            [
                'override_type'  => $data['override_type'],
                'override_label' => $data['override_type'] === 'relabel' ? $data['override_label'] : null,
                'is_required'    => $data['override_type'] === 'change_required' ? (bool) $data['is_required'] : null,
            ]
        );

        return redirect()->route('admin.categories.fields.index', $category)->with('success', 'Override saved.');
    }

    public function destroy(AssetCategory $category, CategoryField $field): RedirectResponse
    {
        $this->authorize('category_fields.manage');

        CategoryFieldOverride::where('category_id', $category->id)
            ->where('category_field_id', $field->id)
            ->delete();

        return redirect()->route('admin.categories.fields.index', $category)->with('success', 'Override removed.');
    }

    private function assertOverridable(AssetCategory $category, CategoryField $field): void
    {
        if ($field->category_id === $category->id) {
            throw ValidationException::withMessages([
                'override_type' => 'A category cannot override a field it directly defines.',
            ]);
        }

        $cursor = $category->parent_id ? AssetCategory::find($category->parent_id) : null;
        $isAncestorField = false;

        while ($cursor) {
            if ($cursor->id === $field->category_id) {
                $isAncestorField = true;
                break;
            }
            $cursor = $cursor->parent_id ? AssetCategory::find($cursor->parent_id) : null;
        }

        if (! $isAncestorField) {
            throw ValidationException::withMessages([
                'override_type' => 'This field is not inherited from an ancestor of this category.',
            ]);
        }
    }
}
