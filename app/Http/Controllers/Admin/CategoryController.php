<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('assets.view');

        $categories = AssetCategory::orderBy('parent_id')->orderBy('sort_order')->orderBy('name')->get();

        $roots = $categories->whereNull('parent_id')->values();
        $tree = $this->flatten($roots, $categories);

        return view('admin.categories.index', compact('tree'));
    }

    public function create(): View
    {
        $this->authorize('assets.create');

        $parents = AssetCategory::orderBy('name')->get();

        return view('admin.categories.form', ['category' => new AssetCategory(), 'parents' => $parents]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('assets.create');

        $data = $request->validate([
            'parent_id'   => 'nullable|exists:asset_categories,id',
            'name'        => 'required|string|max:255',
            'code'         => 'required|string|max:50|alpha_dash|unique:asset_categories,code',
            'asset_prefix' => 'nullable|string|max:20|alpha_dash',
            'description'  => 'nullable|string',
            'sort_order'   => 'nullable|integer|min:0',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['asset_prefix'] = filled($data['asset_prefix'] ?? null) ? strtoupper($data['asset_prefix']) : null;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        AssetCategory::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(AssetCategory $category): View
    {
        $this->authorize('assets.edit');

        $parents = AssetCategory::where('id', '!=', $category->id)->orderBy('name')->get();

        return view('admin.categories.form', compact('category', 'parents'));
    }

    public function update(Request $request, AssetCategory $category): RedirectResponse
    {
        $this->authorize('assets.edit');

        $data = $request->validate([
            'parent_id'   => 'nullable|exists:asset_categories,id|different:id',
            'name'        => 'required|string|max:255',
            'code'         => 'required|string|max:50|alpha_dash|unique:asset_categories,code,' . $category->id,
            'asset_prefix' => 'nullable|string|max:20|alpha_dash',
            'description'  => 'nullable|string',
            'sort_order'   => 'nullable|integer|min:0',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['asset_prefix'] = filled($data['asset_prefix'] ?? null) ? strtoupper($data['asset_prefix']) : null;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(AssetCategory $category): RedirectResponse
    {
        $this->authorize('assets.delete');

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    /**
     * Flatten the parent/child tree into an ordered list with a depth marker for indentation.
     */
    private function flatten(Collection $roots, Collection $all, int $depth = 0): array
    {
        $result = [];

        foreach ($roots as $node) {
            $node->depth = $depth;
            $result[] = $node;
            $children = $all->where('parent_id', $node->id)->values();
            $result = array_merge($result, $this->flatten($children, $all, $depth + 1));
        }

        return $result;
    }
}
