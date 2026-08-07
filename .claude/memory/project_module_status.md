---
name: project-module-status
description: "AssetWise implementation progress — M00/M03/M04/M08 done, M01+M02 partial, M05 or M06 next"
metadata:
  type: project
---

As of 2026-08-07 on the `daniels_branch` branch (kalpeshkhandelwal11/AssetWise): **M00, M03, M04 and M08 are complete; M01 and M02 are partial.** Full suite: **273 tests passing**. **M05 (QR/Barcode) and M06 (Bulk Import/Export) are next** — both unblocked and independent of each other. M09/M11/M13/M16 are also unblocked now that M08 exists.

**Environment (fixed 2026-08-07):** `C:\php83` (PHP 8.3.33) is now first on the USER PATH, so plain `php` resolves to 8.3 in any **new** shell — an already-open terminal keeps the old PATH until reopened. PHP 8.2.33 is still installed via winget (`PHP.PHP.8.2`) but is no longer on PATH; its removal was blocked by the permission prompt and is still pending. Also delete a stale `public/hot` if pages render unstyled.

## M01 / M02 are NOT fully done (audited 2026-08-07 — the status tables previously claimed otherwise)

- **M01:** no user admin UI, no role admin UI, no org-master CRUD. No `UserController`, no `RoleController`, no `/admin/users` or `/admin/roles` routes. Roles and role assignments are seeder/`tinker`-only. `departments`/`branches` exist as tables (added by M03) but aren't in `MasterController::ENTITIES`; `designations` has no table.
- **M02:** `CompanyController::toggleActive()` still has the placeholder comment where the "can't deactivate a company that owns assets" guard belongs (line ~97). M03 was supposed to wire it and didn't.

Both are recorded in `docs/decisions-log.md` under "Outstanding gaps in done modules".

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
