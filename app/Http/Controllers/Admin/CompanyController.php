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

        return view('admin.companies.form', [
            'company'   => new Company(),
            'companies' => Company::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('companies.manage');

        $data = $request->validate($this->rules());

        $data['code'] = strtoupper($data['code']);
        $data['is_head_office'] = $request->boolean('is_head_office');
        Company::create($data);

        return redirect()->route('admin.companies.index')
            ->with('success', 'Company created successfully.');
    }

    public function edit(Company $company): View
    {
        $this->authorize('companies.manage');

        return view('admin.companies.form', [
            'company'   => $company,
            'companies' => Company::where('is_active', true)
                ->where('id', '!=', $company->id)
                ->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('companies.manage');

        $data = $request->validate($this->rules($company));

        // A company cannot be its own parent.
        if ((int) ($data['parent_company_id'] ?? 0) === $company->id) {
            return back()->withInput()
                ->withErrors(['parent_company_id' => 'A company cannot be its own parent.']);
        }

        $data['code'] = strtoupper($data['code']);
        $data['is_head_office'] = $request->boolean('is_head_office');
        $company->update($data);

        return redirect()->route('admin.companies.index')
            ->with('success', 'Company updated successfully.');
    }

    /**
     * Shared validation rules for store/update. Pass the current company on update
     * so its own code and parent are excluded from the uniqueness/self checks.
     */
    private function rules(?Company $company = null): array
    {
        $codeUnique = 'unique:companies,code' . ($company ? ',' . $company->id : '');
        $parentRule = ['nullable', 'exists:companies,id'];
        if ($company) {
            $parentRule[] = 'not_in:' . $company->id;
        }

        return [
            'name'               => 'required|string|max:255',
            'legal_name'         => 'nullable|string|max:255',
            'code'               => "required|string|max:50|alpha_dash|$codeUnique",
            'parent_company_id'  => $parentRule,
            'is_head_office'     => 'boolean',
            'address'            => 'nullable|string|max:500',
            'city'               => 'nullable|string|max:100',
            'country'            => 'nullable|string|max:100',
            'gstin'              => 'nullable|string|size:15',
            'pan'                => 'nullable|string|size:10',
            'cin'                => 'nullable|string|size:21',
            'registered_address' => 'nullable|string|max:500',
            'registered_city'    => 'nullable|string|max:100',
            'registered_state'   => 'nullable|string|max:100',
            'registered_pincode' => 'nullable|string|max:12',
            'registered_country' => 'nullable|string|max:100',
            'contact_name'       => 'nullable|string|max:150',
            'contact_email'      => 'nullable|email|max:150',
            'contact_phone'      => 'nullable|string|max:50',
        ];
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
