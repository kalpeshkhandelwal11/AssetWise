<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalStep;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('roles.manage');

        $roles = Role::withCount(['users', 'permissions'])->orderBy('name')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $this->authorize('roles.manage');

        return view('admin.roles.form', $this->formData(new Role(['guard_name' => 'web'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('roles.manage');

        $data = $request->validate([
            'name'                     => 'required|string|max:255|unique:roles,name',
            'session_lifetime_minutes' => 'nullable|integer|min:1',
            'permissions'              => 'array',
            'permissions.*'            => 'exists:permissions,name',
        ]);

        $permissions = $data['permissions'] ?? [];

        $role = Role::create([
            'name'                     => $data['name'],
            'guard_name'               => 'web',
            'session_lifetime_minutes' => $data['session_lifetime_minutes'] ?? null,
        ]);

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('role')
            ->causedBy($request->user())
            ->performedOn($role)
            ->withProperties(['attributes' => ['permissions' => $permissions]])
            ->log('permissions assigned');

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('roles.manage');

        return view('admin.roles.form', $this->formData($role));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('roles.manage');

        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'session_lifetime_minutes' => 'nullable|integer|min:1',
            'permissions'              => 'array',
            'permissions.*'            => 'exists:permissions,name',
        ]);

        // R1: seeded role cannot be renamed
        if ($role->isLocked() && $data['name'] !== $role->name) {
            return back()->withInput()->with('error', 'Seeded roles cannot be renamed.');
        }

        $permissions = $data['permissions'] ?? [];

        // R3: Super Admin's permission set is immutable — submitted array is ignored
        if ($role->name === 'Super Admin') {
            $permissions = Permission::pluck('name')->all();
        }

        // R6: cannot strip roles.manage from a role you hold
        if ($request->user()->hasRole($role->name)
            && $role->hasPermissionTo('roles.manage')
            && ! in_array('roles.manage', $permissions, true)) {
            return back()->withInput()->with('error', 'You cannot remove roles.manage from a role you hold.');
        }

        $oldName            = $role->name;
        $oldPermissions     = $role->permissions->pluck('name')->sort()->values()->all();
        $newPermissionsSort = collect($permissions)->sort()->values()->all();
        $retargeted         = 0;

        DB::transaction(function () use ($role, $data, $permissions, $oldName, &$retargeted) {
            $role->update([
                'name'                     => $data['name'],
                'session_lifetime_minutes' => $data['session_lifetime_minutes'] ?? null,
            ]);

            // Rename cascade (M08 coupling): approval_steps.approver_role stores a
            // role *name*, not an FK. R1 already freezes seeded names, so this only
            // ever retargets a custom role — cascading beats blocking a referenced
            // custom role from ever being renamed again.
            if ($oldName !== $role->name) {
                $retargeted = ApprovalStep::where('approver_role', $oldName)->update(['approver_role' => $role->name]);
            }

            $role->syncPermissions($permissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($oldPermissions !== $newPermissionsSort) {
            activity('role')
                ->causedBy($request->user())
                ->performedOn($role)
                ->withProperties(['old' => ['permissions' => $oldPermissions], 'attributes' => ['permissions' => $newPermissionsSort]])
                ->log('permissions updated');
        }

        $message = 'Role updated.';
        if ($retargeted > 0) {
            $message .= " {$retargeted} approval step(s) retargeted to the new role name.";
        }

        return redirect()->route('admin.roles.index')->with('success', $message);
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('roles.manage');

        // R2
        if ($role->isLocked()) {
            return back()->with('error', 'Seeded roles cannot be deleted.');
        }

        // R4
        if (ApprovalStep::where('approver_role', $role->name)->exists()) {
            return back()->with('error', 'This role is referenced by an approval workflow step and cannot be deleted.');
        }

        // R5
        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to one or more users and cannot be deleted.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }

    private function formData(Role $role): array
    {
        $grouped = collect(Role::PERMISSION_GROUPS)->flatten()->all();
        $other   = Permission::whereNotIn('name', $grouped)->orderBy('name')->pluck('name')->all();

        $groups = Role::PERMISSION_GROUPS;
        if (! empty($other)) {
            $groups['Other'] = $other;
        }

        return [
            'role'            => $role,
            'permissionGroups'=> $groups,
            'rolePermissions' => $role->exists ? $role->permissions->pluck('name')->all() : [],
        ];
    }
}
