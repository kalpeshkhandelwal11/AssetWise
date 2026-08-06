<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\CategoryField;
use App\Services\DynamicFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryFieldController extends Controller
{
    public function index(AssetCategory $category, DynamicFieldService $fields): View
    {
        $this->authorize('category_fields.manage');

        $ownFields = $category->fields()->orderBy('display_order')->get();
        $inherited = $fields->resolveForCategory($category->id)->filter(fn ($f) => $f->isInherited);
        $overrides = $category->fieldOverrides()->get()->keyBy('category_field_id');
        $ancestorNames = AssetCategory::whereIn('id', $inherited->pluck('definedOnCategoryId'))->pluck('name', 'id');

        return view('admin.category-fields.index', compact('category', 'ownFields', 'inherited', 'overrides', 'ancestorNames'));
    }

    public function create(AssetCategory $category): View
    {
        $this->authorize('category_fields.manage');

        return view('admin.category-fields.form', ['category' => $category, 'field' => new CategoryField()]);
    }

    public function store(Request $request, AssetCategory $category): RedirectResponse
    {
        $this->authorize('category_fields.manage');

        $data = $this->validated($request);
        $this->assertFieldKeyAvailable($category, $data['field_key']);

        DB::transaction(function () use ($category, $data) {
            $field = $category->fields()->create($data);
            $this->syncOptions($field, $data['options'] ?? []);
        });

        return redirect()->route('admin.categories.fields.index', $category)->with('success', 'Field created.');
    }

    public function edit(AssetCategory $category, CategoryField $field): View
    {
        $this->authorize('category_fields.manage');

        $field->load('options');

        return view('admin.category-fields.form', compact('category', 'field'));
    }

    public function update(Request $request, AssetCategory $category, CategoryField $field): RedirectResponse
    {
        $this->authorize('category_fields.manage');

        $data = $this->validated($request);
        $this->assertFieldKeyAvailable($category, $data['field_key'], ignoreFieldId: $field->id);

        if ($data['field_type'] !== $field->field_type && $field->values()->exists()) {
            throw ValidationException::withMessages([
                'field_type' => 'Field type cannot change once assets have saved values for this field.',
            ]);
        }

        DB::transaction(function () use ($field, $data) {
            $typeChangedAwayFromDropdown = $field->field_type === 'dropdown' && $data['field_type'] !== 'dropdown';

            $field->update($data);

            if ($typeChangedAwayFromDropdown) {
                $field->options()->delete();
            } else {
                $this->syncOptions($field, $data['options'] ?? []);
            }
        });

        return redirect()->route('admin.categories.fields.index', $category)->with('success', 'Field updated.');
    }

    public function destroy(AssetCategory $category, CategoryField $field): RedirectResponse
    {
        $this->authorize('category_fields.manage');

        $field->delete();

        return redirect()->route('admin.categories.fields.index', $category)->with('success', 'Field deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'field_key'                  => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label'                      => 'required|string|max:255',
            'field_type'                 => 'required|in:text,number,date,dropdown,boolean,textarea',
            'is_required'                => 'boolean',
            'is_searchable'              => 'boolean',
            'display_order'              => 'nullable|integer|min:0',
            'validation_min'             => 'nullable|numeric',
            'validation_max'             => 'nullable|numeric',
            'validation_decimal_places'  => 'nullable|integer|min:0|max:10',
            'validation_max_length'      => 'nullable|integer|min:1',
            'validation_regex'           => 'nullable|string|max:255',
            'options'                    => 'required_if:field_type,dropdown|array',
            'options.*.value'            => 'required_with:options|string|max:255',
            'options.*.label'            => 'required_with:options|string|max:255',
        ]);

        $rules = array_filter([
            'min'            => $data['validation_min'] ?? null,
            'max'            => $data['validation_max'] ?? null,
            'decimal_places' => $data['validation_decimal_places'] ?? null,
            'max_length'     => $data['validation_max_length'] ?? null,
            'regex'          => $data['validation_regex'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return [
            'field_key'        => $data['field_key'],
            'label'            => $data['label'],
            'field_type'       => $data['field_type'],
            'is_required'      => $request->boolean('is_required'),
            'is_searchable'    => $request->boolean('is_searchable'),
            'display_order'    => $data['display_order'] ?? 0,
            'validation_rules' => $rules ?: null,
            'options'          => $data['options'] ?? [],
        ];
    }

    private function syncOptions(CategoryField $field, array $options): void
    {
        $field->options()->delete();

        foreach (array_values($options) as $i => $option) {
            $field->options()->create([
                'option_value' => $option['value'],
                'option_label' => $option['label'],
                'sort_order'   => $i,
                'is_active'    => true,
            ]);
        }
    }

    private function collidingCategoryIds(AssetCategory $category): array
    {
        $ancestors = [];
        $cursor = $category->parent_id ? AssetCategory::find($category->parent_id) : null;

        while ($cursor) {
            $ancestors[] = $cursor->id;
            $cursor = $cursor->parent_id ? AssetCategory::find($cursor->parent_id) : null;
        }

        $descendants = [];
        $queue = [$category->id];

        while ($queue) {
            $ids = AssetCategory::whereIn('parent_id', $queue)->pluck('id')->all();
            $descendants = array_merge($descendants, $ids);
            $queue = $ids;
        }

        return array_merge($ancestors, $descendants);
    }

    private function assertFieldKeyAvailable(AssetCategory $category, string $key, ?int $ignoreFieldId = null): void
    {
        $ids = $this->collidingCategoryIds($category);

        if (empty($ids)) {
            return;
        }

        $exists = CategoryField::whereIn('category_id', $ids)
            ->where('field_key', $key)
            ->when($ignoreFieldId, fn ($q) => $q->where('id', '!=', $ignoreFieldId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'field_key' => 'This field key is already used by an ancestor or descendant category.',
            ]);
        }
    }
}
