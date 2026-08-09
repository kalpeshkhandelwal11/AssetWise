<?php

namespace Tests\Feature\Admin;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.roles.index'))->assertRedirect('/login');
    }

    public function test_index_requires_permission(): void
    {
        // Asset Manager has masters.manage but not roles.manage — a sharper probe
        // than Viewer for "does the permission check actually gate on roles.manage".
        $assetManager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($assetManager)
             ->get(route('admin.roles.index'))
             ->assertForbidden();
    }

    public function test_store_creates_custom_role_with_permissions(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.roles.store'), [
                 'name'        => 'Reviewer',
                 'permissions' => ['assets.view', 'reports.view'],
             ])
             ->assertRedirect(route('admin.roles.index'))
             ->assertSessionHas('success');

        $role = Role::where('name', 'Reviewer')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('assets.view'));
        $this->assertTrue($role->hasPermissionTo('reports.view'));
        $this->assertFalse($role->hasPermissionTo('assets.delete'));
    }

    public function test_seeded_role_cannot_be_renamed(): void
    {
        $this->seedRolesAndPermissions();
        $auditor = Role::where('name', 'Auditor')->firstOrFail();

        $this->actingAs($this->admin())
             ->put(route('admin.roles.update', $auditor), [
                 'name'        => 'Auditor Renamed',
                 'permissions' => $auditor->permissions->pluck('name')->all(),
             ])
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertEquals('Auditor', $auditor->fresh()->name);
    }

    public function test_seeded_role_cannot_be_deleted(): void
    {
        $this->seedRolesAndPermissions();
        $approver = Role::where('name', 'Approver')->firstOrFail();

        $this->actingAs($this->admin())
             ->delete(route('admin.roles.destroy', $approver))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $approver->id]);
    }

    public function test_super_admin_permissions_are_locked(): void
    {
        $this->seedRolesAndPermissions();
        $superAdmin = Role::where('name', 'Super Admin')->firstOrFail();
        $totalPermissions = Permission::count();

        $this->actingAs($this->admin())
             ->put(route('admin.roles.update', $superAdmin), [
                 'name'        => 'Super Admin',
                 'permissions' => ['assets.view'], // attempt to strip everything else
             ])
             ->assertRedirect(route('admin.roles.index'));

        $this->assertEquals($totalPermissions, $superAdmin->fresh()->permissions()->count());
    }

    public function test_role_referenced_by_approval_step_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.roles.store'), [
                 'name'        => 'Reviewer',
                 'permissions' => ['assets.view'],
             ]);

        $role = Role::where('name', 'Reviewer')->firstOrFail();
        $workflow = ApprovalWorkflow::factory()->module('transfer')->create();
        ApprovalStep::create([
            'workflow_id'   => $workflow->id,
            'level'         => 1,
            'approver_type' => 'role',
            'approver_role' => 'Reviewer',
        ]);

        $this->actingAs($this->admin())
             ->delete(route('admin.roles.destroy', $role))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_role_held_by_a_user_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.roles.store'), [
                 'name'        => 'Reviewer',
                 'permissions' => ['assets.view'],
             ]);

        $role = Role::where('name', 'Reviewer')->firstOrFail();
        $holder = User::factory()->create();
        $holder->assignRole('Reviewer');

        $this->actingAs($this->admin())
             ->delete(route('admin.roles.destroy', $role))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_renaming_custom_role_retargets_approval_steps(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.roles.store'), [
                 'name'        => 'Reviewer',
                 'permissions' => ['assets.view', 'workflow.approve'],
             ]);

        $role = Role::where('name', 'Reviewer')->firstOrFail();
        $workflow = ApprovalWorkflow::factory()->module('transfer')->create();
        $step = ApprovalStep::create([
            'workflow_id'   => $workflow->id,
            'level'         => 1,
            'approver_type' => 'role',
            'approver_role' => 'Reviewer',
        ]);

        $this->actingAs($this->admin())
             ->put(route('admin.roles.update', $role), [
                 'name'        => 'Senior Reviewer',
                 'permissions' => ['assets.view', 'workflow.approve'],
             ])
             ->assertRedirect(route('admin.roles.index'))
             ->assertSessionHas('success');

        $this->assertEquals('Senior Reviewer', $role->fresh()->name);
        $this->assertEquals('Senior Reviewer', $step->fresh()->approver_role);
    }

    public function test_permission_change_takes_effect_immediately(): void
    {
        $viewer = $this->createUserWithRole('Viewer');
        $viewerRole = Role::where('name', 'Viewer')->firstOrFail();

        $this->assertFalse($viewer->fresh()->can('assets.export'));

        $this->actingAs($this->admin())
             ->put(route('admin.roles.update', $viewerRole), [
                 'name'        => 'Viewer',
                 'permissions' => array_merge($viewerRole->permissions->pluck('name')->all(), ['assets.export']),
             ])
             ->assertRedirect(route('admin.roles.index'));

        $this->assertTrue($viewer->fresh()->can('assets.export'));
    }

    public function test_permission_change_is_written_to_activity_log(): void
    {
        $admin = $this->admin();
        $viewerRole = Role::where('name', 'Viewer')->firstOrFail();

        $this->actingAs($admin)
             ->put(route('admin.roles.update', $viewerRole), [
                 'name'        => 'Viewer',
                 'permissions' => array_merge($viewerRole->permissions->pluck('name')->all(), ['assets.export']),
             ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name'     => 'role',
            'subject_type' => Role::class,
            'subject_id'   => $viewerRole->id,
            'description'  => 'permissions updated',
        ]);
    }
}
