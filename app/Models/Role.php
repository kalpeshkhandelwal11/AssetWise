<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use LogsActivity;

    /** Seeded roles — cannot be renamed or deleted. */
    public const SEEDED = [
        'Super Admin', 'Asset Manager', 'Department User', 'Auditor', 'Approver', 'Viewer',
    ];

    /** Alias kept for readability where "locked" (immutable) is the more natural word. */
    public const LOCKED = self::SEEDED;

    /**
     * Permission groups for the per-role matrix editor. Mirrors the module
     * comments in RolePermissionSeeder. Any permission not listed here falls
     * into an "Other" bucket in the UI so it is never invisible/unassignable.
     */
    public const PERMISSION_GROUPS = [
        'Users & Access' => [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.manage', 'login_history.view', 'activity_log.view',
        ],
        'Masters' => [
            'masters.view', 'masters.manage', 'companies.manage',
            'departments.manage', 'branches.manage', 'designations.manage',
        ],
        'Assets' => [
            'assets.view', 'assets.create', 'assets.edit', 'assets.delete',
            'assets.bulk', 'assets.export', 'assets.override_category', 'assets.view_financials',
        ],
        'Category Fields' => [
            'category_fields.manage',
        ],
        'Tags' => [
            'tags.manage',
        ],
        'Movement' => [
            'movement.assign', 'movement.transfer', 'movement.verify',
        ],
        'Audit' => [
            'audit.manage', 'audit.verify',
        ],
        'Maintenance' => [
            'maintenance.manage',
        ],
        'Disposal' => [
            'disposal.request', 'disposal.approve',
        ],
        'Reports' => [
            'reports.view', 'reports.export',
        ],
        'Workflow' => [
            'workflow.approve', 'workflow.manage',
        ],
        'Imports' => [
            'imports.manage',
        ],
    ];

    protected function casts(): array
    {
        return [
            'session_lifetime_minutes' => 'integer',
        ];
    }

    public function isLocked(): bool
    {
        return in_array($this->name, self::LOCKED, true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'session_lifetime_minutes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('role');
    }
}
