<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Install-only. `syncPermissions()` is destructive — running this seeder on a live
 * install silently reverts any permission edit made through the Roles admin UI.
 * Do not add `db:seed` to a routine deployment step once M01's Roles screen ships.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Users & Access
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.manage',
            'login_history.view',
            'activity_log.view',

            // Masters
            'masters.view', 'masters.manage', 'companies.manage',
            'departments.manage', 'branches.manage', 'designations.manage',

            // Assets
            'assets.view', 'assets.create', 'assets.edit', 'assets.delete',
            'assets.bulk', 'assets.export',
            'assets.override_category',
            'assets.view_financials',

            // Category fields
            'category_fields.manage',

            // Tags (M05)
            'tags.generate', 'tags.assign', 'tags.view', 'tags.print', 'tags.replace',
            'settings.manage',

            // Movement
            'movement.assign', 'movement.transfer', 'movement.verify',

            // Audit
            'audit.manage', 'audit.verify',

            // Maintenance
            'maintenance.manage',

            // Disposal
            'disposal.request', 'disposal.approve', 'disposal.complete',

            // Reports
            'reports.view', 'reports.export',

            // Workflow
            'workflow.approve', 'workflow.manage',

            // Imports
            'imports.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Super Admin — all permissions, 8hr session
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->update(['session_lifetime_minutes' => 480]);
        $superAdmin->syncPermissions(Permission::all()); // includes companies.manage, masters.manage

        // Asset Manager
        $assetManager = Role::firstOrCreate(['name' => 'Asset Manager', 'guard_name' => 'web']);
        $assetManager->update(['session_lifetime_minutes' => 480]);
        $assetManager->syncPermissions([
            'assets.view', 'assets.create', 'assets.edit', 'assets.delete',
            'assets.bulk', 'assets.export', 'assets.view_financials',
            'category_fields.manage',
            // Asset Manager is this app's operational-ownership role for physical assets —
            // gets the full tag lifecycle, matching how M08 granted it workflow.approve.
            'tags.generate', 'tags.assign', 'tags.view', 'tags.print', 'tags.replace',
            'settings.manage',
            'movement.assign', 'movement.transfer', 'movement.verify',
            'audit.manage', 'audit.verify',
            'maintenance.manage',
            'disposal.request', 'disposal.complete',
            // The default transfer + tag_replacement workflows (WorkflowSeeder) both route
            // a step to Asset Manager, so the role needs to be able to act on approvals.
            'workflow.approve',
            'reports.view', 'reports.export',
            'imports.manage',
            'masters.view', 'masters.manage', 'companies.manage',
            'departments.manage', 'branches.manage', 'designations.manage',
        ]);

        // Department User
        $deptUser = Role::firstOrCreate(['name' => 'Department User', 'guard_name' => 'web']);
        $deptUser->update(['session_lifetime_minutes' => 480]);
        $deptUser->syncPermissions([
            'assets.view',
            'movement.assign',
            'reports.view',
        ]);

        // Auditor
        $auditor = Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
        $auditor->update(['session_lifetime_minutes' => 480]);
        $auditor->syncPermissions([
            'assets.view',
            'audit.manage', 'audit.verify',
            'reports.view',
            'masters.view',
            'activity_log.view',
        ]);

        // Approver
        $approver = Role::firstOrCreate(['name' => 'Approver', 'guard_name' => 'web']);
        $approver->update(['session_lifetime_minutes' => 480]);
        $approver->syncPermissions([
            'assets.view',
            'workflow.approve',
            'movement.verify',
            'disposal.approve',
            'reports.view',
        ]);

        // Viewer
        $viewer = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $viewer->update(['session_lifetime_minutes' => 480]);
        $viewer->syncPermissions([
            'assets.view',
            'reports.view',
        ]);
    }
}
