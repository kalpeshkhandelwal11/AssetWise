<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('employees.manage');

        $query = Employee::query()->with(['department', 'designation', 'user']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('employee_code', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%"));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $employees = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.employees.index', compact('employees'));
    }

    public function create(): View
    {
        $this->authorize('employees.manage');

        return view('admin.employees.form', $this->formData(new Employee()));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('employees.manage');

        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active', true);
        Employee::create($data);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Employee created successfully.');
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('employees.manage');

        return view('admin.employees.form', $this->formData($employee));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('employees.manage');

        $data = $request->validate($this->rules($employee));
        $data['is_active'] = $request->boolean('is_active', true);
        $employee->update($data);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function toggleActive(Employee $employee): RedirectResponse
    {
        $this->authorize('employees.manage');

        if ($employee->is_active && ($count = $this->activeCustodyCount($employee)) > 0) {
            return back()->with('error', "Cannot deactivate: {$count} active asset(s) are still in this employee's custody.");
        }

        $employee->update(['is_active' => ! $employee->is_active]);

        return back()->with('success', $employee->is_active ? 'Employee activated.' : 'Employee deactivated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('employees.manage');

        if (($count = $this->activeCustodyCount($employee)) > 0) {
            return back()->with('error', "Cannot deactivate: {$count} active asset(s) are still in this employee's custody.");
        }

        // Never hard-delete; always deactivate (custodian history FKs nullOnDelete).
        $employee->update(['is_active' => false]);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Employee deactivated.');
    }

    private function activeCustodyCount(Employee $employee): int
    {
        return $employee->custodiedAssets()->where('is_active', true)->count();
    }

    /**
     * Shared validation rules. On update the current employee is excluded from the
     * employee_code and user_id uniqueness checks.
     */
    private function rules(?Employee $employee = null): array
    {
        $suffix = $employee ? ',' . $employee->id : '';

        return [
            'name'            => 'required|string|max:255',
            'employee_code'   => "required|string|max:50|unique:employees,employee_code$suffix",
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'user_id'         => "nullable|exists:users,id|unique:employees,user_id$suffix",
            'company_id'      => 'nullable|exists:companies,id',
            'department_id'   => 'nullable|exists:departments,id',
            'branch_id'       => 'nullable|exists:branches,id',
            'designation_id'  => 'nullable|exists:designations,id',
            'date_of_joining' => 'nullable|date',
        ];
    }

    private function formData(Employee $employee): array
    {
        // Users not already linked to another employee (plus this employee's own user).
        $linkedElsewhere = Employee::whereNotNull('user_id')
            ->when($employee->exists, fn ($q) => $q->where('id', '!=', $employee->id))
            ->pluck('user_id');

        return [
            'employee'     => $employee,
            'companies'    => Company::where('is_active', true)->orderBy('name')->get(),
            'departments'  => Department::where('is_active', true)->orderBy('name')->get(),
            'branches'     => Branch::where('is_active', true)->orderBy('name')->get(),
            'designations' => Designation::where('is_active', true)->orderBy('name')->get(),
            'users'        => User::where('is_active', true)
                ->whereNotIn('id', $linkedElsewhere)
                ->orderBy('name')->get(),
        ];
    }
}
