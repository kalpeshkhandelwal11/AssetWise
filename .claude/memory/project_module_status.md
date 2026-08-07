---
name: project-module-status
description: "AssetWise implementation progress — M00/M03/M04/M08 done, M01+M02 partial, M01 completion planned and starting next"
metadata:
  type: project
---

As of 2026-08-07 on the `daniels_branch` branch (kalpeshkhandelwal11/AssetWise): **M00, M03, M04 and M08 are complete; M01 and M02 are partial.** Full suite: **273 tests passing**. **M05 (QR/Barcode) and M06 (Bulk Import/Export) are next** — both unblocked and independent of each other. M09/M11/M13/M16 are also unblocked now that M08 exists.

**Environment (fixed 2026-08-07):** `C:\php83` (PHP 8.3.33) is now first on the USER PATH, so plain `php` resolves to 8.3 in any **new** shell — an already-open terminal keeps the old PATH until reopened. PHP 8.2.33 is still installed via winget (`PHP.PHP.8.2`) but is no longer on PATH; its removal was blocked by the permission prompt and is still pending. Also delete a stale `public/hot` if pages render unstyled.

## M01 / M02 are NOT fully done (audited 2026-08-07 — the status tables previously claimed otherwise)

- **M01:** no user admin UI, no role admin UI, no org-master CRUD. No `UserController`, no `RoleController`, no `/admin/users` or `/admin/roles` routes. Roles and role assignments are seeder/`tinker`-only. `departments`/`branches` exist as tables (added by M03) but aren't in `MasterController::ENTITIES`; `designations` has no table.
- **M02:** `CompanyController::toggleActive()` still has the placeholder comment where the "can't deactivate a company that owns assets" guard belongs (line ~97). M03 was supposed to wire it and didn't.

Both are recorded in `docs/decisions-log.md` under "Outstanding gaps in done modules".

## M01 completion — scope decisions (confirmed with the user 2026-08-08)

Full plan: `docs/planning/modules/M01-implementation-plan.md`. Development starts 2026-08-09. Four decisions were settled before planning:

1. **Designations: create fully** — migration + model + seed + admin screen + `designation_id` on `users`. (Spec'd in M01 and MASTER_PLAN UF-02; nothing consumes it today, but users need the field.)
2. **Org masters: reuse `MasterController::ENTITIES`**, not the dedicated `DepartmentController`/`BranchController`/`DesignationController` the M01 routes table names. All three are byte-identical in shape to the 7 entities already in the registry, so they inherit the existing list/search/Alpine-modal screens for ~6 lines. **The M01 module doc must be amended, not silently diverged from.** URLs become `/admin/masters/departments` etc.
3. **Roles: full CRUD with guards** — create/rename/delete + permission matrix + `session_lifetime_minutes`. The 6 seeded roles cannot be renamed or deleted; Super Admin's permission set is immutable; roles referenced by `approval_steps.approver_role` or held by users cannot be deleted. Renaming a *custom* role **cascades** to `approval_steps` in a transaction rather than being blocked.
4. **Activity log: log changes + build the global viewer** at `/admin/activity-log`, wiring up the dead sidebar placeholder.

**Traps found during planning that the implementation must respect:**
- 🔴 `logFillable()` on `User` would write **password hashes** into `activity_log` — `password` is in `$fillable` and `LogsActivity` ignores `$hidden`. Use an explicit `logOnly([...])` allow-list. Do not copy `Asset::getActivitylogOptions()` verbatim.
- 🔴 `syncRoles`/`syncPermissions` are pivot writes and fire **no model events** — adding `LogsActivity` alone will never log a role change. Those must be manual `activity()` calls.
- 🔴 `RolePermissionSeeder` uses destructive `syncPermissions`, so once roles are UI-editable, `php artisan db:seed` on a live install **silently reverts every admin permission edit**. Must be documented as install-only (the deployment doc currently lists it as a routine deploy step).
- `User` needs `->dontSubmitEmptyLogs()` or every login writes an empty row (`last_login_at` churn).
- Two pre-existing bugs to sweep up: the Administration sidebar group is gated `masters.manage` while the Shared Masters link inside it needs `masters.view`, so **Auditor can never see the group**; and `admin.login-history.index` has had no nav entry since it shipped.

## What's in M08 (done) — Approval Workflow Engine

Polymorphic multi-level approval engine. Migrations: `approval_workflows`, `approval_steps`, `approval_requests` (morphs to `approvable`), `approval_actions` (append-only, `created_at` only, `$timestamps = false` — copies `LoginHistory`).

- **`App\Services\WorkflowService`** — `submit / approve / reject / escalate / canAct / activate / pendingFor / hasEscalated`. Constructor-injects `NotificationService`.
- **The hand-off is an event, not a call.** M08 never invokes `TagService`/`MovementService` — those don't exist. `ApprovalRequestApproved` fires **only on the terminal step**, synchronously (no `ShouldQueue`); consumers add an auto-discovered listener and switch on `$event->request->workflow->module`. Also `ApprovalRequestSubmitted`, `...Rejected`, `...Escalated`.
- **Escalation widens, never skips** (decision P8.1) — once escalated, the original approver *and* the next level's approver can both act on the same step; on the last step it falls back to `workflow.manage` holders so Super Admin is always a way out. "Has escalated" is *derived* from an `escalate` row existing for `(request_id, step_level)` — no boolean flag — which is also what makes `escalate()` idempotent.
- **Rejection is terminal** (decision P8.2) — no edit-in-place; re-submitting means calling `submit()` again for a fresh row.
- **Exactly one active workflow per module** — `submit()` throws `ValidationException` on 0 or >1; `activate()` deactivates module siblings so the admin UI maintains the invariant.
- **`App\Services\NotificationService`** — M12's Phase 1 stub, database channel only, via `GenericNotification` which overrides `databaseType()` so `notifications.type` holds `approval.pending` etc., not the PHP class name.
- `approvals:escalate` command, registered in `bootstrap/app.php` via a **newly added** `->withSchedule()` block (the file had none).
- `WorkflowSeeder` seeds `transfer` and `tag_replacement` only; `disposal`/`kit_assignment` are left unconfigured on purpose as proof the engine is configurable without code changes. Companion fix: `workflow.approve` added to Asset Manager (both defaults route a step to it — they'd have 403'd).

**Non-obvious things worth remembering:**
- `nextStepAfter()` walks to the next-highest level, **not** `level + 1` — workflow levels are only unique-per-workflow, not contiguous.
- `escalate()`'s due check (`started_at + escalation_hours < now()`) runs in PHP, not SQL — adding a *column* number of hours to a timestamp has no portable form across MySQL and the SQLite test suite. The join + `whereNotExists` stay in SQL.
- The inbox filters eligibility with `canAct()` in PHP, not SQL — a deliberate, documented scaling deferral to revisit when M09/M13 produce real volume.
- Route param is `{approval_request}`, not `{request}` — `{request}` would shadow the `Illuminate\Http\Request` that approve/reject also need.
- `ApprovalRequestPolicy::view()` must admit past actors, not just `canAct()` — `canAct()` goes false the instant a request resolves, which locked an approver out of the trail they'd just written. Found by browser smoke test, not by the unit tests.
- Use partial `Event::fake([X::class])` in tests; a bare `Event::fake()` also swallows the Eloquent model events `LogsActivity` needs.

**How to apply:** pick M05 (`docs/planning/modules/M05-qr-barcode.md` — its replacement flow can now go straight in) or M06 (`docs/planning/modules/M06-bulk-import-export.md`). Read the module spec plus its "Pending Decisions" block in `docs/decisions-log.md`, resolve open `P#.#` items with the user before finalizing a plan, then build in the established layering (thin controllers → `app/Services/` → `app/Policies/` only for instance-scoped abilities).
