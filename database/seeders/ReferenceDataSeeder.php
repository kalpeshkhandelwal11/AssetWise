<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Additive, idempotent reference data that a LIVE install must also receive.
 *
 * Unlike RolePermissionSeeder (install-only — syncPermissions() is destructive), everything
 * here only ever adds: firstOrCreate for permissions/workflows, givePermissionTo for grants.
 * It never revokes or overwrites an admin's edits, so it is safe to run on every deploy via
 * `php artisan assetwise:sync-reference-data` (tools/deploy/deploy-update.sh).
 *
 * Fresh installs get it through DatabaseSeeder (last, after the install-only seeders).
 */
class ReferenceDataSeeder extends Seeder
{
    /** permission => roles that receive it on first creation (Super Admin always does). */
    public const PERMISSIONS = [
        'companies.view_all' => [],
    ];

    /**
     * Workflows that must exist (module => [name, steps]). Created active only when the module
     * has no workflow at all — an admin who deactivated or rebuilt one is left alone.
     */
    public const WORKFLOWS = [
        'asset_creation' => ['Standard Asset Creation Approval', [
            ['level' => 1, 'approver_type' => 'role', 'approver_role' => 'Approver',      'escalation_hours' => 48],
            ['level' => 2, 'approver_type' => 'role', 'approver_role' => 'Asset Manager', 'escalation_hours' => 48],
        ]],
        'asset_loss' => ['Standard Loss Write-off Approval', [
            ['level' => 1, 'approver_type' => 'role', 'approver_role' => 'Approver',      'escalation_hours' => 48],
            ['level' => 2, 'approver_type' => 'role', 'approver_role' => 'Asset Manager', 'escalation_hours' => 48],
        ]],
    ];

    /** System statuses (code => attributes), created if missing. */
    public const STATUSES = [
        'IN_TRANSIT' => ['name' => 'In Transit', 'color' => '#f97316', 'is_system' => true],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name => $roles) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            foreach (['Super Admin', ...$roles] as $roleName) {
                $role = Role::where('name', $roleName)->first();

                if ($role && $permission->wasRecentlyCreated) {
                    $role->givePermissionTo($permission);
                } elseif ($role && $roleName === 'Super Admin' && ! $role->hasPermissionTo($permission)) {
                    // Super Admin is "every permission" by contract (RoleController enforces it).
                    $role->givePermissionTo($permission);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::STATUSES as $code => $attributes) {
            \App\Models\AssetStatus::firstOrCreate(['code' => $code], $attributes + ['is_active' => true]);
        }

        // LOST now means "written off as lost" (asset_loss approval) — rename only if untouched.
        \App\Models\AssetStatus::where('code', 'LOST')->where('name', 'Lost')->update(['name' => 'Lost (Written Off)']);

        // Companies Act Schedule II classes (life in years) — added if missing, never overwritten.
        foreach (\App\Models\DepreciationClass::SCHEDULE_II as $class) {
            \App\Models\DepreciationClass::firstOrCreate(['code' => $class['code']], $class + ['is_depreciable' => true, 'is_active' => true]);
        }

        foreach (self::WORKFLOWS as $module => [$name, $steps]) {
            if (ApprovalWorkflow::where('module', $module)->exists()) {
                continue;
            }

            $workflow = ApprovalWorkflow::create(['module' => $module, 'name' => $name, 'is_active' => true]);

            foreach ($steps as $step) {
                $workflow->steps()->create($step);
            }
        }
    }
}
