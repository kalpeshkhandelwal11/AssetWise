<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepreciationClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Companies Act Schedule II classes — name, useful life, depreciable flag (depreciation.manage). */
class DepreciationClassController extends Controller
{
    public function index(): View
    {
        $this->authorize('depreciation.manage');

        return view('admin.depreciation-classes.index', [
            'classes' => DepreciationClass::withCount('categories')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('depreciation.manage');

        DepreciationClass::create($this->validated($request));

        return back()->with('success', 'Schedule II class added.');
    }

    public function update(Request $request, DepreciationClass $class): RedirectResponse
    {
        $this->authorize('depreciation.manage');

        $class->update($this->validated($request, $class));

        return back()->with('success', "\"{$class->name}\" updated.");
    }

    private function validated(Request $request, ?DepreciationClass $class = null): array
    {
        $data = $request->validate([
            'name'              => 'required|string|max:100',
            'code'              => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('depreciation_classes', 'code')->ignore($class?->id)],
            'useful_life_years' => 'nullable|required_if:is_depreciable,1|numeric|min:0.5|max:100',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_depreciable'] = $request->boolean('is_depreciable');
        $data['is_active'] = $request->boolean('is_active', true);

        if (! $data['is_depreciable']) {
            $data['useful_life_years'] = null;
        }

        return $data;
    }
}
