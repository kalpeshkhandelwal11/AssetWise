<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('companies.manage');

        $query = Company::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $companies = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.companies.index', compact('companies'));
    }

    public function create(): View
    {
        $this->authorize('companies.manage');

        return view('admin.companies.form', ['company' => new Company()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('companies.manage');

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'required|string|max:50|unique:companies,code|alpha_dash',
            'address'       => 'nullable|string|max:500',
            'city'          => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'contact_name'  => 'nullable|string|max:150',
            'contact_email' => 'nullable|email|max:150',
            'contact_phone' => 'nullable|string|max:50',
        ]);

        $data['code'] = strtoupper($data['code']);
        Company::create($data);

        return redirect()->route('admin.companies.index')
            ->with('success', 'Company created successfully.');
    }

    public function edit(Company $company): View
    {
        $this->authorize('companies.manage');

        return view('admin.companies.form', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('companies.manage');

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'required|string|max:50|alpha_dash|unique:companies,code,' . $company->id,
            'address'       => 'nullable|string|max:500',
            'city'          => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'contact_name'  => 'nullable|string|max:150',
            'contact_email' => 'nullable|email|max:150',
            'contact_phone' => 'nullable|string|max:50',
        ]);

        $data['code'] = strtoupper($data['code']);
        $company->update($data);

        return redirect()->route('admin.companies.index')
            ->with('success', 'Company updated successfully.');
    }

    public function toggleActive(Company $company): RedirectResponse
    {
        $this->authorize('companies.manage');

        if ($company->is_active) {
            $assetCount = $company->assets()->count();
            if ($assetCount > 0) {
                return back()->with('error', "Cannot deactivate: {$assetCount} asset(s) are still assigned to this company.");
            }
        }

        $company->update(['is_active' => ! $company->is_active]);

        $msg = $company->is_active ? 'Company activated.' : 'Company deactivated.';

        return back()->with('success', $msg);
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('companies.manage');

        $assetCount = $company->assets()->count();
        if ($assetCount > 0) {
            return back()->with('error', "Cannot deactivate: {$assetCount} asset(s) are still assigned to this company.");
        }

        // Never hard-delete; always deactivate
        $company->update(['is_active' => false]);

        return redirect()->route('admin.companies.index')
            ->with('success', 'Company deactivated.');
    }
}
