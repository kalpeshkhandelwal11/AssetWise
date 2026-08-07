# M01 — User & Access: Completion Plan

> Planning only — implementation starts in a separate session. Approved 2026-08-08; four scope decisions were resolved with the user before this plan was finalised (see "Scope decisions" below).

## Context

M01 is marked 🟡 partial. What shipped in the original build: the extended `User` model, `CheckSessionLifetime` and `ForcePasswordChange` middleware, login-history capture + listing, the deactivated-account login guard, and a seeded role/permission matrix.

What never shipped: **every administration screen**. There is no `UserController`, no `RoleController`, no `/admin/users`, no `/admin/roles`. Today a Super Admin cannot create a user or edit a role through the UI — it has to be done in `tinker` or a seeder. Departments and branches have tables (added by M03) but no admin screen; `designations` has no table at all. The sidebar carries three dead `#` placeholders (Users, Roles & Permissions, Activity Log) and Login History has a route with no nav entry.

This is real carried debt, not a blocker: M05/M06 can proceed without it. But M08's approver routing depends on roles being assignable, and every module from here on assumes self-service user management.

**Outcome:** M01 moves to ✅ done — a Super Admin can manage users, roles, permissions and org masters entirely through the UI, with an audited trail of every change.

---

## Scope decisions (confirmed with the user)

| # | Decision | Choice |
|---|----------|--------|
| 1 | **Designations** | **Create fully** — migration + model + seed + admin screen + `designation_id` on users. Matches the M01 spec, MASTER_PLAN UF-02 and the BRD masters list. |
| 2 | **Org masters** | **Reuse `MasterController::ENTITIES`** — add 3 slugs to the existing registry rather than building 3 near-identical controllers. URLs become `/admin/masters/departments` etc. The spec's routes table predates `MasterController`; the doc gets amended. |
| 3 | **Roles screen** | **Full CRUD with guards.** Create/rename/delete custom roles + permission matrix + `session_lifetime_minutes`. The 6 seeded roles cannot be renamed or deleted; roles referenced by `approval_steps.approver_role` cannot be deleted. |
| 4 | **Activity log** | **Log changes + build the global viewer.** `LogsActivity` on `User`, manual logging of role/permission changes, plus `/admin/activity-log` with filters. |

---

## Findings that shape the design

Five things verified in the code that change how this must be built:

1. **`LogsActivity` will NOT capture role assignment.** `syncRoles`/`assignRole`/`syncPermissions` are pivot writes — Laravel fires no model event. **All role/permission logging must be manual via the `activity()` helper.** Adding the trait alone does not satisfy "log role/permission changes".
2. **🔴 `logFillable()` on `User` would leak password hashes into `activity_log`.** `password` is in `User::$fillable` and `LogsActivity` does not respect `$hidden`. Use an explicit `logOnly([...])` allow-list. Do **not** copy `Asset::getActivitylogOptions()` verbatim.
3. **`User` needs `->dontSubmitEmptyLogs()`.** `LogSuccessfulLogin` writes `last_login_at` on every login; with an allow-list excluding it, every login would otherwise create an empty `updated` row.
4. **The permission cache already auto-flushes.** Spatie's `RefreshesPermissionCache` + `syncRoles`/`syncPermissions` handle it. Keep explicit `forgetCachedPermissions()` calls as intent-documentation, not as the mechanism.
5. **`MasterController::destroy()` hard-deletes**, and `assets.department_id`/`branch_id` are `nullOnDelete`. Adding org masters to `ENTITIES` without a usage guard makes silent data loss a two-click operation.

Two pre-existing bugs to sweep up:
- `sidebar-nav.blade.php:243` — the Administration group is gated `masters.manage`, but the Shared Masters link inside it is gated `masters.view`. **The Auditor role can never see the group its own link lives in.**
- `admin.login-history.index` has existed since the original M01 build with **no sidebar entry at all**.

---

## Design

### 1. Migrations

- `2026_08_07_100001_create_designations_table.php` — `id, name, code(50) unique, is_active default true, timestamps`. Structurally identical to `departments`/`branches` so `MasterController`'s generic validation works with zero special-casing.
- `2026_08_07_100002_add_org_fields_to_users_table.php` — `department_id`, `branch_id`, `designation_id`, all `nullable()->constrained()->nullOnDelete()`.

`nullOnDelete` (not `restrict`) matches `assets.department_id` exactly; the "don't orphan" protection lives in the application-layer usage guard, consistent with the rest of the codebase. Designations must migrate before the users ALTER (SQLite validates the referenced table).

### 2. Models

- **New `app/Models/Designation.php`** — copy of `Department.php` + `users(): HasMany`.
- **`Department.php` / `Branch.php`** — add `users()` and `assets()` relations (needed by the usage guard and list filters).
- **`app/Models/User.php`** — `$fillable` += 3 org FKs; `department()`/`branch()`/`designation()`/`custodiedAssets()`/`createdAssets()` relations; `use LogsActivity` with:
  ```php
  LogOptions::defaults()
      ->logOnly(['name','email','is_active','mfa_enabled','must_change_password',
                 'department_id','branch_id','designation_id'])
      ->logOnlyDirty()
      ->dontSubmitEmptyLogs()
      ->useLogName('user');
  ```
- **New `app/Models/Role.php`** extending `Spatie\Permission\Models\Role`. Justified: it carries the `SEEDED`/`LOCKED` guard lists and the `PERMISSION_GROUPS` matrix map (same convention as `ApprovalWorkflow::MODULES`), hosts `LogsActivity`, and casts `session_lifetime_minutes` to integer. Register via `config/permission.php` line 5.
  - **Verified safe**: the 3 files importing `Spatie\Permission\Models\Role` (`WorkflowController`, `WorkflowService`, `RolePermissionSeeder`) all query the same table through `getRoleClass()`-agnostic paths. Update their imports to `App\Models\Role` anyway so there's one Role class.

### 3. Controllers

**`Admin\UserController`** — mirrors `CompanyController` exactly (authorize first line of every method, inline `validate()`, shared form branching on `$user->exists`, `destroy()` deactivates). Methods: `index/create/store/edit/update/toggleActive/resetPassword/destroy`, no `show()`.

Guards (all `back()->with('error', …)`, the `MasterController::toggleActive` convention — these are business rules with an explanation, not authorization failures):

| | Guard |
|---|---|
| G1 | Cannot deactivate yourself |
| G2 | Cannot change your own roles |
| G3 | Cannot deactivate/de-role the **last active Super Admin** |
| G4 | Cannot deactivate a user who is **custodian of active assets** (blocks; reassign first) |
| G5 | **Warn only** if the user is a named approver on `approval_steps.approver_user_id` |

Password uses `Password::defaults()`; on update it's `nullable` and `unset()` when blank so the existing hash survives. `must_change_password` defaults to checked on create (matches `AdminUserSeeder`).

**`Admin\RoleController`** — one form, one submit (name + session lifetime + `permissions[]`). No separate permission endpoint.

| | Guard |
|---|---|
| R1 | Seeded role cannot be **renamed** |
| R2 | Seeded role cannot be **deleted** |
| R3 | **Super Admin permissions immutable** — submitted array ignored, forced to `Permission::all()` |
| R4 | Role referenced by an `approval_steps.approver_role` cannot be deleted |
| R5 | Role held by users cannot be deleted |
| R6 | Cannot strip `roles.manage` from a role you hold |

**Rename cascade (M08 coupling).** `approval_steps.approver_role` stores a role *name* string. R1 freezes seeded names, so rename only hits custom roles — which *can* be referenced. **Cascade inside a transaction** (`update` the role, then retarget matching steps) and flash the count, rather than blocking. Blocking would make a referenced custom role permanently unrenamable; cascading preserves M08's lookup invariant.

**`Admin\ActivityLogController`** — read-only, modelled on `LoginHistoryController`'s `when()` chaining. Filters: log_name, event, causer, subject_type, date range. `latest('id')` not `latest()` (same-second determinism). **Null-subject hazard:** `subject_returns_soft_deleted_models` is false and `Asset` soft-deletes, so the blade must use `$activity->subject?->name` with a `class_basename() . ' #' . id` fallback.

**`Admin\MasterController`** — add 3 slugs plus two optional config keys following the existing `has_color`/`has_system` idiom:
- `permission` (defaults `masters.manage` → existing 7 entities byte-for-byte unchanged) so org masters get their own grants. Requires moving `resolveEntity()` above `authorize()`; `landing()` becomes permission-filtered with `abort_if($entities->isEmpty(), 403)`.
- `usage` — `[[table, column], …]` checked in `destroy()` before deleting; blocks with "in use by N record(s), deactivate instead". Existing entities have no `usage` key → returns 0 → unchanged.

### 4. Routes

Inside the existing `admin.` group, after `login-history`:
```php
Route::resource('users', UserController::class)->except(['show']);
Route::patch('users/{user}/toggle', [UserController::class,'toggleActive'])->name('users.toggle');
Route::patch('users/{user}/reset-password', [UserController::class,'resetPassword'])->name('users.reset-password');
Route::resource('roles', RoleController::class)->except(['show']);
Route::get('activity-log', [ActivityLogController::class,'index'])->name('activity-log.index');
```

### 5. Views

Five new blades: `admin/users/{index,form}`, `admin/roles/{index,form}`, `admin/activity-log/index`. `admin/masters/{index,landing}` are **reused unchanged** — org masters inherit the existing Alpine modal screens automatically.

Reuse `x-input-label`, `x-text-input`, `x-input-error`, `x-primary-button`. There is **no `x-select` component** — use raw `<select>` with the class string from `admin/workflows/partials/step-modal.blade.php:54`.

**Permission matrix** (`roles/form.blade.php`): ~40 permissions × N roles is unusable as one grid, so the editor is **per-role**, with permissions grouped into cards by `Role::PERMISSION_GROUPS` (mirroring the seeder's own module comments). Each cell shows the friendly label *and* the raw `module.action` string — the raw string is what appears in every `authorize()` call and in the docs. An **"Other" bucket catches unmatched permissions** so a future module's permission can never become invisible and unassignable. Per-group "select all" via plain Alpine. Locked roles render an amber banner with all boxes checked + disabled.

### 6. Seeders

- `RolePermissionSeeder` — add `activity_log.view`, `departments.manage`, `branches.manage`, `designations.manage`. Asset Manager gets the three org grants; **Auditor** gets `activity_log.view`. Super Admin picks all up via `Permission::all()`.
  - Separate org permissions rather than folding into `masters.manage`: org masters are the only `ENTITIES` feeding a `users` FK, and widening `masters.manage` would silently grant that to Asset Manager and every future custom role.
- `SharedMastersSeeder` — 7 designations, same `firstOrCreate` idiom as the departments/branches loops.
- No factories for the three org models — existing tests build these with `Model::create([...])`.

### 7. Sidebar (`resources/views/layouts/sidebar-nav.blade.php`)

Four edits: widen the group gate at line 243 (fixes the Auditor bug); wire Users → `admin.users.index` and split Roles onto its own `@can('roles.manage')` gate (lines 256-265); **add the missing Login History link**; gate + wire Activity Log (lines 288-291). No `href="#"` should remain in the Administration group.

---

## Build order

Each step must be green before the next.

1. Migrations + `Designation` + Department/Branch relations → `migrate:fresh --seed`
2. **`App\Models\Role` + `config/permission.php` + 3 import swaps → full existing 273 tests must pass.** Highest-risk step (swaps a class M08 uses) — land it isolated, before any new surface exists.
3. Seeder changes → verify grants in `tinker`
4. `MasterController` registry + guards → existing `MasterTest` (11) must pass, then add new cases
5. `UserController` + routes + views + `User` model
6. `RoleController` + routes + views
7. `ActivityLogController` + route + view
8. Sidebar + docs

---

## Tests

New: `tests/Feature/Admin/{UserTest,RoleTest,ActivityLogTest}.php`. Extend existing `MasterTest.php` (don't create a new file). Plain PHPUnit, `use RefreshDatabase, SeedsRolesAndPermissions`, access-control triad on each.

Highest-value cases:
- **`test_activity_log_never_records_the_password_hash`** — the regression test for finding #2, the single most important test in the set
- `test_role_change_is_written_to_activity_log` — proves finding #1 was handled
- `test_renaming_custom_role_retargets_approval_steps` — the M08-coupling regression
- `test_cannot_deactivate_user_who_is_custodian_of_assets` / `test_can_deactivate_user_after_assets_reassigned`
- `test_admin_cannot_deactivate_self`, `test_cannot_deactivate_last_active_super_admin`
- `test_super_admin_permissions_are_locked` — POST a stripped array, assert nothing was removed
- `test_org_master_requires_its_own_permission` — proves the `permission` key works and didn't leak
- `test_permission_change_takes_effect_immediately` — proves the cache flush
- `test_department_in_use_by_an_asset_cannot_be_deleted`
- `test_renders_row_whose_subject_was_soft_deleted` — the null-subject regression
- `test_index_requires_permission` for roles should probe **Asset Manager** (has `masters.manage`, lacks `roles.manage`) — sharper than Viewer

⚠️ `SeedsRolesAndPermissions` calls the seeder **without** `WithoutModelEvents`, so with `LogsActivity` on `Role` every test class starts with 6 role-activity rows. **Scope activity assertions by `log_name`/description, never total counts.** Do not "fix" this by adding `WithoutModelEvents` to the seeder — it would also suppress `RefreshesPermissionCache`.

---

## Verification

```powershell
php artisan migrate:fresh --seed
php artisan route:list --path=admin      # users.*, roles.*, activity-log.index
php artisan test                          # full suite green
npm run build
```

Manual end-to-end (`admin@assetwise.test` / `Admin@1234`):
1. Administration group shows Users, Roles & Permissions, Login History, Activity Log — **no `href="#"` left**
2. Create a user (dept Finance, designation Analyst, must-change on) → log in as them → forced password change → sidebar shows no Administration group
3. Roles → grant `assets.export` to Viewer → a Viewer sees it **in the same request cycle** (proves cache flush)
4. Super Admin matrix is read-only; renaming `Auditor` blocked; deleting `Approver` blocked
5. Create role `Reviewer` → point a workflow step at it → rename → flash reports "1 step retargeted" → step now reads the new name → delete blocked (in use)
6. Make a user a custodian → deactivation blocked with the count → reassign → succeeds. Self-deactivation blocked.
7. Shared Masters → three new cards → delete a department in use → blocked; deactivate → succeeds
8. **Activity Log → expand a password-change row → confirm no hash present**
9. Log in as a deactivated user → "Your account has been deactivated."

---

## Risks

| # | Risk | Sev | Mitigation |
|---|------|-----|------------|
| R-1 | `logFillable()` writes password hashes to `activity_log` | 🔴 | Explicit `logOnly()` + dedicated regression test |
| R-2 | Role assignment silently never logged (pivot writes fire no events) | 🔴 | Manual `activity()` calls in both controllers |
| R-3 | **`db:seed` on a live install reverts UI permission edits** — `syncPermissions` is destructive | 🔴 | New debt this work creates. Document as install-only in the seeder docblock, decisions log, and `deployment-shared-hosting.md` (which currently lists `db:seed` as a routine deploy step) |
| R-4 | Swapping `config/permission.php` to `App\Models\Role` regresses M08 | 🟡 | Verified safe; land as isolated step 2 with all 273 green |
| R-5 | Hard-deleting a department nulls `assets.department_id` | 🟡 | `usage` guard in `MasterController::destroy()` |
| R-6 | Renaming a role orphans `approval_steps.approver_role` | 🟡 | R1 freeze + cascade-in-transaction + regression test |
| R-7 | Admin self-lockout | 🟡 | G1/G2/G3 + R3/R6. Recovery needs `tinker` on shared hosting |
| R-8 | `activity_log` grows unbounded | 🟢 | `activitylog:clean` is **not** scheduled. Consider adding to `withSchedule()` next to `approvals:escalate` |

---

## Docs to update

- **`docs/planning/modules/M01-user-access.md`** — tick the 5 open tasks; **rewrite the routes table** (the 3 dedicated org controllers never happen — replace with one `MasterController` slug-registry row); correct the permission list to the concrete `.manage` names; delete the "M01 is partially complete" block-quote.
- **`docs/decisions-log.md`** — remove the 4 M01 rows from the gaps table (**M02's row stays**); rewrite the "Impact on M08" note; add an "M01 — completion decisions" section covering the registry approach, the custom Role model, G1–G5 and R1–R6, the rename-cascade choice, the `syncPermissions` clobber caveat, and the password-leak rule.
- **`CLAUDE.md`** — M01 → ✅ done; update the test count; add the new permissions to the RBAC section with a note that seeded roles are rename/delete-protected.
- **`README.md`**, **`docs/planning/MODULES_INDEX.md`** — M01 → ✅ done, drop the M01 gap bullets.
- **`docs/deployment-shared-hosting.md`** — add the `db:seed` caveat.
- **`.claude/memory/project_module_status.md`** — flip M01 from partial to done once implemented (the four scope decisions are already recorded there).

### Spec conflicts to record, not silently diverge from
1. `M01-user-access.md:31-33` specifies dedicated `DepartmentController`/`BranchController`/`DesignationController`. Scope decision #2 overrides this — the doc must be amended.
2. `M01-user-access.md:23` implies four-verb CRUD permissions per entity (`departments.*`). The shipped convention is coarse `.manage` for everything except `users.*`. Following the code; note it so nobody hunts for `roles.create`.
