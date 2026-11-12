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

        // Same guard as the Deactivate button: the Active checkbox must not be a way around it.
        if ($employee->is_active && ! $data['is_active'] && ($count = $this->activeCustodyCount($employee)) > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'is_active' => "Cannot deactivate: {$count} active asset(s) are still in this employee's custody.",
            ]);
        }

        $employee->update($data);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function toggleActive(Employee $employee): RedirectResponse
    {
        $this->authorize('employees.manage');

        if ($employee->is_active && ($count = $this->activeCustodyCount($employee)) > 0) {
            return redirect()->route('admin.employees.clearance', $employee)
                ->with('error', "Cannot deactivate yet: {$count} asset(s) are still in this employee's custody. Return them here first.");
        }

        $employee->update(['is_active' => ! $employee->is_active]);

        return back()->with('success', $employee->is_active ? 'Employee activated.' : 'Employee deactivated.');
    }

    /**
     * Exit clearance: what a leaving employee still holds, and one action to raise the return
     * for all of it. Deactivation stays blocked until custody is empty; this is the way through.
     */
    public function clearance(Employee $employee): \Illuminate\View\View
    {
        $this->authorize('employees.manage');

        $assets = $this->custody($employee);
        $states = [];
        $returnable = 0;

        foreach ($assets as $asset) {
            $states[$asset->id] = match (true) {
                $asset->isInTransit()        => ['label' => 'In transit — awaiting receipt', 'color' => 'indigo'],
                $asset->hasPendingMovement() => ['label' => 'Movement pending approval', 'color' => 'amber'],
                $asset->isDraft()            => ['label' => 'Draft — not yet approved', 'color' => 'gray'],
                default                      => ['label' => 'With employee', 'color' => 'red'],
            };
            if ($states[$asset->id]['label'] === 'With employee') {
                $returnable++;
            }
        }

        return view('admin.employees.clearance', [
            'employee'   => $employee,
            'assets'     => $assets,
            'states'     => $states,
            'returnable' => $returnable,
            'locations'  => \App\Models\Location::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function clearanceReturn(Request $request, Employee $employee, \App\Services\MovementService $movements): RedirectResponse
    {
        $this->authorize('employees.manage');
        $this->authorize('movement.assign');

        $data = $request->validate([
            'to_location_id' => 'nullable|exists:locations,id',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $type = \App\Models\MovementType::where('code', 'RETURN')->where('is_active', true)->first();
        if (! $type) {
            return back()->with('error', 'The Return movement type is not set up.');
        }

        $assets = $this->custody($employee)
            ->filter(fn ($a) => ! $a->isInTransit() && ! $a->hasPendingMovement() && ! $a->isDraft())
            ->values();

        if ($assets->isEmpty()) {
            return back()->with('error', 'Nothing left to return — every asset already has a movement in progress.');
        }

        // One batch = one approval for the whole clearance (ValidationException from the
        // workflow or a guard surfaces through the error blocks on the page).
        $movements->submitBatch($assets, [
            'movement_type_id' => $type->id,
            'to_location_id'   => $data['to_location_id'] ?? null,
            'notes'            => $data['notes'] ?? 'Exit clearance',
        ], $request->user());

        return redirect()->route('admin.employees.clearance', $employee)
            ->with('success', "Return raised for {$assets->count()} asset(s) — awaiting approval.");
    }

    /** Live assets in the employee's custody (retired ones no longer count — see DisposalService::finalize). */
    private function custody(Employee $employee): \Illuminate\Support\Collection
    {
        return $employee->custodiedAssets()->where('is_active', true)->with(['status', 'location'])->orderBy('name')->get()
            ->reject(fn ($a) => $a->isRetired())->values();
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('employees.manage');

        if (($count = $this->activeCustodyCount($employee)) > 0) {
            return redirect()->route('admin.employees.clearance', $employee)
                ->with('error', "Cannot deactivate yet: {$count} asset(s) are still in this employee's custody. Return them here first.");
        }

        // Never hard-delete; always deactivate (custodian history FKs nullOnDelete).
        $employee->update(['is_active' => false]);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Employee deactivated.');
    }

    private function activeCustodyCount(Employee $employee): int
    {
        return $this->custody($employee)->count();
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
