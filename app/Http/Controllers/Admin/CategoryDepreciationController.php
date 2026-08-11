<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\DepreciationMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryDepreciationController extends Controller
{
    public function edit(AssetCategory $category): View
    {
        $this->authorize('depreciation.manage');

        return view('modules.depreciation.category-edit', [
            'category' => $category,
            'default'  => $category->depreciationDefault,
            'methods'  => DepreciationMethod::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, AssetCategory $category): RedirectResponse
    {
        $this->authorize('depreciation.manage');

        $data = $request->validate([
            'depreciation_method_id' => ['required', Rule::exists('depreciation_methods', 'id')->where('is_active', true)],
            'useful_life_months'     => ['required', 'integer', 'min:1'],
            'salvage_value'          => ['nullable', 'numeric', 'min:0'],
            'salvage_percent'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'start_basis'            => ['required', Rule::in(['purchase_date', 'commission_date'])],
        ]);

        $category->depreciationDefault()->updateOrCreate(['category_id' => $category->id], $data);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Depreciation defaults saved for \"{$category->name}\".");
    }
}
