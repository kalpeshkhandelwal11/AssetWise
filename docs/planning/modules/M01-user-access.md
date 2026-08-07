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

`users.*`, `roles.*`, `departments.*`, `branches.*`, `designations.*`, `login_history.view`, `assets.override_category`, `workflow.approve`, `category_fields.manage`

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/admin/users` | UserController |
| CRUD | `/admin/roles` | RoleController |
| CRUD | `/admin/departments` | DepartmentController |
| CRUD | `/admin/branches` | BranchController |
| CRUD | `/admin/designations` | DesignationController |
| GET | `/admin/login-history` | LoginHistoryController@index |

## Tasks

- [x] Extend User model + migration
- [ ] **User CRUD with role assignment** — ❌ not built: no `UserController`, no `/admin/users` route, no views
- [ ] **Role CRUD + permission matrix UI** — ❌ not built: no `RoleController`, no `/admin/roles` route. Roles/permissions exist only via `RolePermissionSeeder`
- [ ] **Org master CRUD (3 entities)** — ❌ not built: `departments` and `branches` tables exist (added by M03) but are absent from `MasterController::ENTITIES`; `designations` has no table at all
- [x] LoginHistory listener on Login/Failed events
- [x] Seed default roles: Super Admin, Asset Manager, Department User, Auditor, Approver, Viewer
- [ ] **Activity log on user/role changes** — ❌ blocked on the two CRUD screens above
- [ ] **Deactivate user (no hard delete if linked to assets)** — ❌ blocked on user CRUD

> **M01 is partially complete.** What shipped: the extended `User` model, session-lifetime middleware, forced password change, login-history capture and listing, and the seeded role/permission matrix. What did not: every *administration screen* for users, roles and org masters. Today a Super Admin cannot create a user or edit a role through the UI — it has to be done in `tinker` or a seeder. Close this gap before any module that assumes self-service user management, and note that M08's role-based approver routing depends on roles being assignable.

## Acceptance criteria

- Super Admin can manage users and roles
- Permission middleware blocks unauthorized routes
- Login history records successful and failed attempts
- Default roles seeded and assignable

## Parallel note

Dev 2 can start M08 design/branch once permission seeder is merged (needs `workflow.approve` permission names).
