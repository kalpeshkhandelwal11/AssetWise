# M01 — User & Access Management

| | |
|--|--|
| **Developer** | Dev 1 |
| **Phase** | 1 |
| **Depends on** | M00 |
| **Blocks** | M02, M08, all RBAC-gated modules |

## Scope

Authentication, users, roles, permissions, org masters (departments, branches, designations), login history, session basics.

## DB Tables

- `users` (extend: branch_id, department_id, designation_id, is_active)
- `departments`, `branches`, `designations`
- `login_histories` (user_id, ip, user_agent, success, logged_at)
- Spatie: `roles`, `permissions`, pivots

## Permissions to seed

`users.view`, `users.create`, `users.edit`, `users.delete`, `roles.manage`, `departments.manage`, `branches.manage`, `designations.manage`, `login_history.view`, `activity_log.view`, `assets.override_category`, `workflow.approve`, `category_fields.manage`

> The spec originally implied four-verb CRUD permissions per org entity (`departments.*`). The shipped convention is a single coarse `.manage` permission per entity — matching every other master in `MasterController::ENTITIES` — except `users.*`, which keeps its four verbs because create/edit/delete carry materially different guards (see the implementation plan).

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/admin/users` | `Admin\UserController` |
| PATCH | `/admin/users/{user}/toggle` | `Admin\UserController@toggleActive` |
| PATCH | `/admin/users/{user}/reset-password` | `Admin\UserController@resetPassword` |
| CRUD | `/admin/roles` | `Admin\RoleController` |
| GET | `/admin/masters/departments` | `Admin\MasterController` (registry slug — no dedicated `DepartmentController`) |
| GET | `/admin/masters/branches` | `Admin\MasterController` (registry slug — no dedicated `BranchController`) |
| GET | `/admin/masters/designations` | `Admin\MasterController` (registry slug — no dedicated `DesignationController`) |
| GET | `/admin/login-history` | `Admin\LoginHistoryController@index` |
| GET | `/admin/activity-log` | `Admin\ActivityLogController@index` |

> Departments, branches and designations were originally specced as three dedicated controllers. They turned out to be byte-identical in shape to the 7 entities already registered in `MasterController::ENTITIES` (name, code, is_active), so they were added as registry slugs instead — each with its own `permission` key and a `usage` guard in `destroy()` so a hard-delete can't silently null a `users`/`assets` FK. See `docs/planning/modules/M01-implementation-plan.md` and the decisions log for the full rationale.

## Tasks

- [x] Extend User model + migration
- [x] **User CRUD with role assignment** — `Admin\UserController`, `/admin/users`, views at `resources/views/admin/users/`
- [x] **Role CRUD + permission matrix UI** — `Admin\RoleController`, `/admin/roles`, per-role permission matrix at `resources/views/admin/roles/form.blade.php`
- [x] **Org master CRUD (3 entities)** — `departments`, `branches`, `designations` all registered in `MasterController::ENTITIES`; `designations` table + model added
- [x] LoginHistory listener on Login/Failed events
- [x] Seed default roles: Super Admin, Asset Manager, Department User, Auditor, Approver, Viewer
- [x] **Activity log on user/role changes** — `User` and `Role` both use `LogsActivity`; role/permission pivot changes are logged manually via `activity()` since `syncRoles`/`syncPermissions` fire no model events. Global viewer at `/admin/activity-log`
- [x] **Deactivate user (no hard delete if linked to assets)** — `UserController` guards block deactivating a user who is custodian of active assets, the last active Super Admin, or yourself

M01 is now ✅ done. Completion work landed 2026-08-09 — see `docs/planning/modules/M01-implementation-plan.md` for the design and `docs/decisions-log.md` for the "M01 — completion decisions" section.

## Acceptance criteria

- Super Admin can manage users and roles
- Permission middleware blocks unauthorized routes
- Login history records successful and failed attempts
- Default roles seeded and assignable

## Parallel note

Dev 2 can start M08 design/branch once permission seeder is merged (needs `workflow.approve` permission names).
