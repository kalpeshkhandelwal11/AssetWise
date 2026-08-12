<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalStep;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('users.view');

        $query = User::with(['roles', 'department', 'branch', 'designation']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roleOptions' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('users.create');

        return view('admin.users.form', $this->formData(new User(['is_active' => true, 'must_change_password' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('users.create');

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255|unique:users,email',
            'password'       => ['required', Password::defaults()],
            'department_id'  => 'nullable|exists:departments,id',
            'branch_id'      => 'nullable|exists:branches,id',
            'designation_id' => 'nullable|exists:designations,id',
            'roles'          => 'required|array|min:1',
            'roles.*'        => 'exists:roles,name',
        ]);

        $roles = $data['roles'];
        unset($data['roles']);

        $data['password']             = Hash::make($data['password']);
        $data['is_active']            = $request->boolean('is_active');
        $data['must_change_password'] = $request->boolean('must_change_password');
        $data['email_verified_at']    = now();

        $user = User::create($data);
        $user->syncRoles($roles);

        activity('user')
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties(['attributes' => ['roles' => $roles]])
            ->log('roles assigned');

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        $this->authorize('users.edit');

        return view('admin.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('users.edit');

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'       => ['nullable', Password::defaults()],
            'department_id'  => 'nullable|exists:departments,id',
            'branch_id'      => 'nullable|exists:branches,id',
            'designation_id' => 'nullable|exists:designations,id',
            'roles'          => 'required|array|min:1',
            'roles.*'        => 'exists:roles,name',
        ]);

        $roles = $data['roles'];
        unset($data['roles']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
            $data['password_changed_at'] = now();
        }

        $data['is_active']            = $request->boolean('is_active');
        $data['must_change_password'] = $request->boolean('must_change_password');

        $isSelf         = $user->is($request->user());
        $oldRoles       = $user->getRoleNames()->sort()->values()->all();
        $newRoles       = collect($roles)->sort()->values()->all();
        $rolesChanged   = $oldRoles !== $newRoles;
        $deactivating   = $user->is_active && ! $data['is_active'];
        $removesSuperAdmin = $user->hasRole('Super Admin') && ! in_array('Super Admin', $roles, true);

        // G1: cannot deactivate yourself
        if ($isSelf && $deactivating) {
            return back()->withInput()->with('error', 'You cannot deactivate your own account.');
        }

        // G2: cannot change your own roles
        if ($isSelf && $rolesChanged) {
            return back()->withInput()->with('error', 'You cannot change your own roles.');
        }

        // G3: cannot deactivate/de-role the last active Super Admin
        if (($deactivating || $removesSuperAdmin) && $this->isLastActiveSuperAdmin($user)) {
            return back()->withInput()->with('error', 'Cannot remove or deactivate the last active Super Admin.');
        }

        // G4: cannot deactivate a user who is custodian of active assets
        if ($deactivating && $this->activeCustodyCount($user) > 0) {
            return back()->withInput()->with('error', 'Cannot deactivate: this user is custodian of active assets. Reassign them first.');
        }

        $user->update($data);

        if ($rolesChanged) {
            $user->syncRoles($roles);

            activity('user')
                ->causedBy($request->user())
                ->performedOn($user)
                ->withProperties(['old' => ['roles' => $oldRoles], 'attributes' => ['roles' => $newRoles]])
                ->log('roles updated');
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('users.edit');

        // G1
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->is_active) {
            // G3
            if ($this->isLastActiveSuperAdmin($user)) {
                return back()->with('error', 'Cannot deactivate the last active Super Admin.');
            }

            // G4
            $custodyCount = $this->activeCustodyCount($user);
            if ($custodyCount > 0) {
                return back()->with('error', "Cannot deactivate: custodian of {$custodyCount} active asset(s). Reassign them first.");
            }
        }

        $user->update(['is_active' => ! $user->is_active]);

        $response = back()->with('success', $user->is_active ? 'User activated.' : 'User deactivated.');

        // G5: warn only — does not block
        if (! $user->is_active && $this->isNamedApprover($user)) {
            $response->with('warning', 'This user is a named approver on one or more approval steps.');
        }

        return $response;
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('users.edit');

        $data = $request->validate([
            'password' => ['required', Password::defaults()],
        ]);

        $user->update([
            'password'             => Hash::make($data['password']),
            'password_changed_at'  => now(),
            'must_change_password' => true,
        ]);

        return back()->with('success', 'Password reset. The user must change it at next login.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('users.delete');

        // G1
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        // G3
        if ($this->isLastActiveSuperAdmin($user)) {
            return back()->with('error', 'Cannot deactivate the last active Super Admin.');
        }

        // G4
        if ($this->activeCustodyCount($user) > 0) {
            return back()->with('error', 'Cannot deactivate: this user is custodian of active assets. Reassign them first.');
        }

        $user->update(['is_active' => false]);

        return redirect()->route('admin.users.index')->with('success', 'User deactivated.');
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        if (! $user->is_active || ! $user->hasRole('Super Admin')) {
            return false;
        }

        return User::role('Super Admin')
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }

    private function activeCustodyCount(User $user): int
    {
        return $user->custodiedAssets()->where('assets.is_active', true)->count();
    }

    private function isNamedApprover(User $user): bool
    {
        return ApprovalStep::where('approver_user_id', $user->id)->exists();
    }

    private function formData(User $user): array
    {
        return [
            'user'         => $user,
            'roles'        => Role::orderBy('name')->get(),
            'userRoles'    => $user->exists ? $user->getRoleNames()->all() : [],
            'departments'  => Department::where('is_active', true)->orderBy('name')->get(),
            'branches'     => Branch::where('is_active', true)->orderBy('name')->get(),
            'designations' => Designation::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
