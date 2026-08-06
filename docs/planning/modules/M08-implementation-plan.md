# M08 — Approval Workflow Engine

## Context

M00–M04 are built and merged on `daniels_branch` (auth/RBAC, shared masters, asset master, dynamic EAV fields). Per the module dependency graph, M08 needs only M00+M01 (both done) and is itself the hard blocker for M09 (Asset Movement) and M13 (Disposal), with M05's tag-replacement phase and M17 (Kits) as later consumers too. **No consumer module exists yet** — this is the critical constraint on the design: M08 must be fully self-contained and event-driven, not calling into `TagService`/`MovementService`/etc. that don't exist.

This plan is for review only — implementation happens in a separate session. Do not start coding from this plan.

Four pre-implementation decisions were resolved with the user before finalizing this plan (`docs/decisions-log.md`'s P8.1/P8.2 were open; the other two are new gaps this module surfaced):

- **P8.1 — Escalation authority:** once a step escalates, BOTH the original approver and the escalation target can act — whichever acts first resolves the step (escalation widens eligibility for the same step, it doesn't skip a level).
- **P8.2 — Reject → resubmit:** rejection is terminal for that `approval_requests` row. No edit-in-place; the consumer calls `submit()` again later, creating a fresh row. Keeps `approval_actions` a clean per-attempt log.
- **Notifications:** M12 doesn't exist yet (only a bare `notifications` table + `User` already has `Notifiable`). Build a minimal `NotificationService::send()` now using Laravel's built-in database notification channel — the sidebar's notification bell already expects this (`auth()->user()->unreadNotifications`, wired but never fed).
- **Approver routing:** no department-head field exists anywhere (no `department_id` on `User`, no `head_user_id` on `Department`). Role-based routing only for MVP — seed workflows using `approver_type=role`, approximating the spec's "Dept Head" step with the existing "Approver" role.

**Required companion fix discovered during research:** `RolePermissionSeeder.php` does not currently grant `workflow.approve` to the "Asset Manager" role (only "Approver" and Super Admin have it), but both seeded default workflows below route a step to Asset Manager. Without this fix, Asset Manager users would match a step's role but get 403'd before ever reaching it.

---

## Schema (no detailed column table exists in the planning docs for this module, unlike M04 — designed from the module doc's field lists + this codebase's conventions)

**`approval_workflows`**: `name`, `module` enum(`transfer,disposal,tag_replacement,kit_assignment`), `is_active` (bool, default true). No soft-deletes — mirrors `Company`: never hard-delete, `destroy()` deactivates. Index on `(module, is_active)`.

**`approval_steps`**: `workflow_id` FK cascade, `level` (tinyint, 1-based), `approver_type` enum(`role,user`), `approver_role` (nullable string — Spatie role **name**, not an FK, since roles are queried by name elsewhere in this app), `approver_user_id` (nullable FK users), `escalation_hours` (nullable int; null = never escalates). `unique(workflow_id, level)`.

**`approval_requests`**: `workflow_id` FK restrict, `approvable_type`/`approvable_id` via `$table->morphs('approvable')` (auto-indexes), `status` enum(`pending,approved,rejected`, default pending), `current_step` (tinyint), `current_step_started_at` (timestamp — drives escalation timing, set on create and every step advance), `submitted_by` FK restrict. Index on `(status, current_step)` for inbox queries.

**`approval_actions`** (append-only — copies `LoginHistory`'s exact pattern, confirmed in `app/Models/LoginHistory.php` + its migration): `request_id` FK cascade, `step_level` (tinyint), `user_id` (nullable FK, null = system/escalation), `action` enum(`approve,reject,escalate`), `comment` (nullable text), `created_at` only (`useCurrent()`, no `updated_at`, `public $timestamps = false` on the model). No separate `acted_at` column — `created_at` **is** the act timestamp, matching the existing audit-log convention. "Already escalated for this step" is derived from whether an `escalate` action row exists for `(request_id, step_level=current_step)` — no redundant boolean flag needed. Index on `(request_id, step_level, action)`.

---

## Build order

### 1. Migrations
Four new files after the last M04 migration (`2026_08_06_10001{1,2,3,4}`), per the schema above.

### 2. Models
`ApprovalWorkflow` (`steps()` HasMany ordered by level, `requests()` HasMany), `ApprovalStep` (`workflow()`, `approverUser()`), `ApprovalRequest` (`workflow()`, `approvable()` MorphTo, `actions()` HasMany latest-first, `submittedBy()`; `currentStepDefinition()` helper; `getApprovableLabelAttribute()` — calls an optional `getApprovalLabel()` method on the approvable model if it exists, else falls back to `class_basename($model) . ' #' . $id` — this is the forward-compat hook future consumer models can implement for a nicer inbox display), `ApprovalAction` (copies `LoginHistory`'s `$timestamps=false` pattern exactly).

### 3. `App\Services\WorkflowService`
Business-rule violations throw `ValidationException::withMessages([...])` (matches this codebase's existing convention, e.g. `CategoryFieldController`); eligibility failures throw `AuthorizationException`. No new custom exception classes.

- **`submit(Model $approvable, string $module, User $actor): ApprovalRequest`** — requires exactly one active workflow for `$module` (0 or >1 both throw `ValidationException` — "configurable without code changes" means the admin UI must maintain this invariant, not the service silently picking one). Creates the request at the lowest step level, sets `current_step_started_at = now()`, fires `ApprovalRequestSubmitted`, notifies step-1 approvers.
- **`approve(ApprovalRequest $request, User $actor, ?string $comment = null): ApprovalRequest`** — guards `status==='pending'` and `canAct()`. Logs the action. If a next step exists: advances `current_step`, resets `current_step_started_at`, notifies new approvers. If it's the final step: sets `status='approved'`, fires `ApprovalRequestApproved` **after** eager-loading `workflow`+`approvable` — this event *is* the "call the domain service" mechanism the spec describes, since no concrete `TagService`/`MovementService` exists yet for M08 to call directly. Returns the fresh model (small deliberate deviation from the spec's `void`-ish signature — the controller needs it for the redirect).
- **`reject(ApprovalRequest $request, User $actor, string $comment): ApprovalRequest`** — comment required (poor audit trail otherwise). Sets `status='rejected'`, fires `ApprovalRequestRejected`. Terminal per the P8.2 decision — no resubmit path in the service.
- **`canAct(User $user, ApprovalRequest $request): bool`** — public; the single source of truth `ApprovalRequestPolicy` delegates to. Direct match against the current step (role/user); if not matched AND the step has already escalated (per the derivation above), also checks the level+1 step's approver — or, if there is no level+1 (escalating on the last step), falls back to anyone holding `workflow.manage` as the universal escalation catch-all (Super Admin always qualifies, guaranteeing a way out).
- **`escalate(): void`** — one set-based query (join `approval_steps` on `workflow_id`+`current_step`, filter `status=pending`, `escalation_hours` not null, `current_step_started_at + escalation_hours < now()`, and a `whereNotExists` against `approval_actions` for an existing `escalate` row on this request+step — this `whereNotExists` is also what makes repeat runs idempotent). For each match: records one `escalate` action (`user_id=null`), notifies the widened eligibility set, fires `ApprovalRequestEscalated`.
- **`activate(ApprovalWorkflow $workflow): void`** — extra method beyond the literal spec, needed to keep "exactly one active workflow per module" actually true: in a transaction, deactivates any other active workflow for the same module, then activates this one. Called from the admin controller instead of blocking the admin with a validation error.

### 4. Events (`app/Events/`, new directory — no `EventServiceProvider` wiring needed, listeners auto-discover via typed `handle()` params per this codebase's existing convention)
`ApprovalRequestSubmitted(ApprovalRequest $request, User $submittedBy)`, `ApprovalRequestApproved(ApprovalRequest $request, User $finalApprover)` (fires **only** on the terminal approval, never on intermediate advances — `$event->request->workflow->module` + `$event->request->approvable` is exactly what M09/M05/M13/M17 will listen for later), `ApprovalRequestRejected(ApprovalRequest $request, User $rejectedBy, string $reason)`, `ApprovalRequestEscalated(ApprovalRequest $request, ApprovalStep $step)`. Fire synchronously (no `ShouldQueue`) so a future listener runs inline within the same request/response cycle — this is the actual mechanism satisfying "final approval returns success to caller" without M08 having compile-time knowledge of not-yet-built classes.

### 5. Controllers, policy, routes
- **`Admin\WorkflowController`** — full CRUD, inline `$this->authorize('workflow.manage')` (no dedicated policy — matches `CompanyController`/`CategoryController`). `destroy()` deactivates, never hard-deletes. `store`/`update` call `WorkflowService::activate()` when `is_active` is checked.
- **`Admin\WorkflowStepController`** — nested under a workflow (`workflows/{workflow}/steps`), one step per request — directly mirrors M04's `CategoryFieldController` nesting pattern. Validation: unique `level` per workflow, `approver_role` `required_if:approver_type,role|exists:roles,name`, `approver_user_id` `required_if:approver_type,user|exists:users,id`.
- **`Approvals\ApprovalController`** (new namespace, matches the existing plural-noun convention like `Assets/`) — `index()` (inbox), `show()` (detail + full `approval_actions` history — reasonable addition beyond the spec's minimal route list), `approve()`, `reject()`. Uses `ApprovalRequestPolicy::act()` since eligibility is instance-scoped, unlike the flat-permission admin CRUD above.
- **`App\Policies\ApprovalRequestPolicy`** — `view()` (submitter or an eligible approver) and `act()` (delegates entirely to `WorkflowService::canAct()` — one source of truth).
- **Routes** — `Route::resource('workflows', WorkflowController::class)->except(['show'])` + nested `workflows/{workflow}/steps/*` inside the existing `admin.` prefix group; a new top-level `Route::prefix('approvals')->name('approvals.')` group with `{approval_request}` as the bound param name (not `{request}` — that would shadow the `Illuminate\Http\Request $request` every approve/reject method also needs).
- **Inbox query**: fetch `pending` requests (reasonably pre-scoped) and filter with `canAct()` in PHP rather than a fully SQL-expressed version, since eligibility now depends on the per-row escalation-derived state — a correct pure-SQL version would roughly double the complexity of the `escalate()` query above for a feature with no realistic volume yet (zero consumer modules live). Flagged as a known, deliberate scaling deferral, not an oversight.

### 6. Views
- `admin/workflows/index.blade.php` + `form.blade.php` — same table/form shell as `admin/companies`. Steps are added via the existing `x-modal` component (`resources/views/components/modal.blade.php`) on the workflow edit page, not a separate CRUD screen — a step is only 4 fields, proportionate to a modal.
- `approvals/index.blade.php` — inbox table: `$request->approvable_label`, workflow name, current step description, submitted-by, age; inline approve/reject POST forms with a comment field (required on reject).
- `approvals/show.blade.php` — detail + the append-only `approval_actions` timeline.
- **Sidebar nav fixes** (`resources/views/layouts/sidebar-nav.blade.php`, both confirmed present): promote "Approvals Queue" (currently a dead `#` placeholder buried inside the Movement group, line ~115) to its own top-level item gated by `@can('workflow.approve')`, with a pending-count badge computed inline (same pattern as the notification bell's unread count in `app.blade.php` — no View Composers exist in this codebase, stay consistent). Fix the existing **mis-gated** "Workflow Config" placeholder (line 266 — currently `@can('workflow.approve')`, should be `@can('workflow.manage')`) and point it at `route('admin.workflows.index')`.

### 7. Escalation scheduling
`App\Console\Commands\EscalateApprovals` (`approvals:escalate`) — thin wrapper calling `WorkflowService::escalate()`. **`bootstrap/app.php` currently has no `->withSchedule()` call at all (confirmed)** — must be added, registering `$schedule->command('approvals:escalate')->daily();`. Consistent with `CLAUDE.md`'s single-cron-entry shared-hosting deployment note — `schedule:run` picks this up automatically once registered, no deployment doc changes needed.

### 8. Seeders
New `database\seeders\WorkflowSeeder.php`, registered in `DatabaseSeeder` after `RolePermissionSeeder` (role names must exist first): seeds exactly two workflows, matching the spec's task list —
- **transfer**: step 1 role=`Approver` (48h escalation), step 2 role=`Asset Manager` (48h) — approximates the spec's "Dept Head → Asset Manager" using the closest existing role, documented in a code comment.
- **tag_replacement**: step 1 role=`Asset Manager` (48h), step 2 role=`Super Admin` (48h).

`disposal` and `kit_assignment` get no default workflow — M13/M17 (or an admin) configure those later via the same UI, which is itself proof the "no code changes" acceptance criterion holds.

**Companion fix**: add `'workflow.approve'` to Asset Manager's `syncPermissions([...])` array in the existing `RolePermissionSeeder.php` (currently missing — see Context section).

### 9. Tests
Use `Asset` (existing factory) as the stand-in `approvable` throughout, since `WorkflowService` must be genuinely model-agnostic and no real consumer model exists pre-M09.
- `tests/Unit/Services/WorkflowServiceTest.php` — single-active-workflow invariant (0/1/>1 cases); step advancement + `current_step_started_at` reset; terminal approval sets status + fires `ApprovalRequestApproved` (`Event::fake()`); reject sets status + fires event + leaves the `Asset` record untouched; `canAct()` direct match, pre-escalation denial, post-escalation widening (both old and new approver can act), last-step-escalation fallback to `workflow.manage`; `escalate()` idempotency (run twice, assert one action row).
- `tests/Unit/Services/NotificationServiceTest.php` — `send()` writes a database notification with the passed-in `$type`, not the PHP class name.
- `tests/Feature/Admin/WorkflowControllerTest.php` + `WorkflowStepControllerTest.php` — CRUD, `workflow.manage` gating, activate-deactivates-sibling behavior, step validation.
- `tests/Feature/Approvals/ApprovalControllerTest.php` — inbox correctness including escalation-widened visibility, approve/reject happy paths, 403s for ineligible/unpermitted users, reject requires a comment.
- `tests/Feature/Console/EscalateApprovalsCommandTest.php` — `$this->travel()` past `escalation_hours`, assert one escalation + no duplicate on a second immediate run.

All feature tests use the existing `SeedsRolesAndPermissions` trait + `createUserWithRole()`, matching every prior module's convention.

---

## Verification (for the implementation session)
- `php artisan migrate:fresh --seed` runs clean with the 4 new tables + `WorkflowSeeder`'s 2 default workflows.
- `php artisan test` — full suite green, no regressions to M00–M04 (205 existing tests).
- New test files above all pass, including the escalation idempotency and eligibility-widening cases.
- Manual smoke via `claude-in-chrome`: submit an `Asset` through `WorkflowService::submit($asset, 'transfer', $user)` (e.g. via `artisan tinker` or a throwaway test route), confirm it lands in the "Approver" role's `/approvals` inbox, approve step 1 as an Approver-role user, confirm it advances to step 2 and appears in an Asset Manager's inbox, approve step 2 and confirm `status=approved` + `ApprovalRequestApproved` fired; separately, travel time past `escalation_hours`, run `php artisan approvals:escalate`, confirm the original AND the escalated-to approver can both now act.
