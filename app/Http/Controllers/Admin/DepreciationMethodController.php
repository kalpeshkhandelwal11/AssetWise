<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepreciationMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepreciationMethodController extends Controller
{
    public function index(): View
    {
        $this->authorize('depreciation.manage');

        return view('modules.depreciation.methods', [
            'methods' => DepreciationMethod::orderBy('name')->get(),
        ]);
    }

    /** Flip a method active/inactive — inactive methods stay visible but aren't selectable on forms. */
    public function toggle(DepreciationMethod $method): RedirectResponse
    {
        $this->authorize('depreciation.manage');

        $method->update(['is_active' => ! $method->is_active]);

        return redirect()
            ->route('admin.depreciation-methods.index')
            ->with('success', "\"{$method->name}\" is now " . ($method->is_active ? 'active' : 'inactive') . '.');
    }
}
