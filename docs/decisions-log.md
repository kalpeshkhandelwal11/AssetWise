# AssetWise — Decisions Log

Running record of architectural and implementation decisions across all modules. Each entry records **what** was decided, **why**, and **when**. Pending decisions flag what must be answered before a module can start.

---

## Architectural Decisions (Expert Panel — confirmed before build started)

| # | Question | Decision | Rationale |
|---|----------|----------|-----------|
| A1 | Custom field inheritance in category tree? | **Inherit with override** — child categories inherit parent fields; child can hide or override label/required | Avoids field duplication; mirrors standard EAV patterns |
| A2 | Category change after asset has custom data? | **Block by default** — locked once `asset_field_values` exist; only `assets.override_category` permission can force-change; does NOT migrate values | Prevents silent data loss on EAV rows tied to old category |
| A3 | Asset Type vs Category? | **Independent** — `asset_type_id` and `category_id` are separate FKs on `assets`; category drives custom fields, type is a simple classification | Per BRD: IT Hardware vs "Laptop" are orthogonal axes |
| A4 | Field definition changes after data exists? | **Soft-delete only** — `deleted_at` on `category_fields`; deactivated fields hidden on new asset forms; shown read-only on existing assets | Hard delete would orphan EAV rows with no label |
| A5 | Bulk import with per-category fields? | **Per-category Excel template** — template header = core columns + resolved custom columns for the selected category | One template per category avoids a confusing mega-sheet |
| A6 | Transfer approval required? | **Always required** — every asset movement (assign, return, transfer, inter-company) goes through multi-level approval before taking effect | Audit trail + control requirement from BRD |
| A7 | QR/Barcode lifecycle? | **Pre-generate tag pool** — bulk generate tags into `available` pool, print labels, assign to assets later. Tag replacement requires approval workflow | Physical labels printed before assets registered; decouples tagging from registration |
| A8 | Depreciation configuration? | **Category default + per-asset override** — category sets method/useful life/salvage; each asset can override | Mirrors real-world practice; reduces per-asset data entry |
| A9 | Depreciation methods in MVP? | **Straight-line only** — strategy-pattern architecture allows adding methods without schema changes; others seeded inactive | Simplest method for MVP; architecture ready for WDV etc. |
| A10 | Kit/bundle assignment? | **Both** — saved kit templates (named, reusable) AND ad-hoc bundles (pick assets at assignment time) | Per BRD: both use cases exist |
| A11 | Kit assignment approval mode? | **Configurable** — admin setting `kit_assignment_approval_mode`: `single` (one approval for whole kit) or `per_asset` (one approval per asset) | Different orgs have different governance needs |

---

## M01 — Auth & RBAC

### Implementation choices (made during build, 2026-07-31)

| Decision | Choice | Why |
|----------|--------|-----|
| Session lifetime storage | `_last_activity_at` stored in PHP session | Avoids a DB round-trip on every request; no persistent store needed |
| Session expiry timing | `_last_activity_at` set on first **authenticated GET**, not on login POST | At login POST time the user is not yet authenticated when middleware runs; cannot seed the key there |
| Event listener registration | Auto-discovery only (via typed `handle()`) — never manually register in `AppServiceProvider` | Manual + auto = double-firing; caused 2 login history rows per login |
| Carbon 3 diffInMinutes | Use `$past->diffInMinutes(now())` not `now()->diffInMinutes($past)` | Carbon 3 changed `$absolute` default to `false`; reversed order gives positive value |
| Factory password | `Admin@1234` in `UserFactory` | Must satisfy global password policy (min 8, mixed case, numbers, symbols) set in `AppServiceProvider::boot()` |

---

## M02 — Shared Masters

### Implementation choices (made during build, 2026-07-31)

| Decision | Choice | Why |
|----------|--------|-----|
| Generic master CRUD | Single `MasterController` with entity-registry array — not one controller per entity | 7 entities share identical schema (name, code, is_active); registry pattern avoids 7 near-identical controllers |
| Location hierarchy controller | Single `LocationController` handles all 4 levels (location/building/floor/room) | All operations redirect to the same locations index page; grouping in one controller keeps related logic together |
| Master table deletion | `is_active` toggle only — no hard delete for master records | FK integrity: statuses/types are referenced by assets in later modules; deactivation hides without breaking references |
| System status protection | `is_system` boolean on `asset_statuses` — system statuses block deactivation and deletion | Available/Assigned/In Maintenance/Disposed are required by application logic in all downstream modules |
| Company deactivation | Toggle-active only (no hard delete); "cannot deactivate if assets exist" is a **placeholder comment** in `CompanyController::toggleActive()` | Assets table doesn't exist yet in M02; M03 must wire in the actual `$company->assets()->exists()` check |
| Separate `companies.manage` permission | `companies.manage` distinct from `masters.manage` | Companies have more business weight (own assets, drive inter-company transfers) — finer access control needed |
| `masters.view` permission | Added for Auditor and Viewer roles | Read-only access to master landing page for reporting/verification users |

---

## M03 — Asset Master

### Pre-implementation decisions (confirmed 2026-07-31)

| Decision | Choice | Why |
|----------|--------|-----|
| Departments & Branches | Create as new master tables now (`departments`, `branches` — name, code, is_active) | `assets.department_id` and `assets.branch_id` are FK columns per master plan; need the tables before the migration |
| Category tree UI | Parent-dropdown list (not drag-and-drop tree) | Same pattern as M02 masters; covers real-world use; drag-and-drop is a M07 UI enhancement |
| Location cascade | API endpoints in `routes/api.php` — `GET /api/buildings?location_id=X`, `/api/floors?building_id=X`, `/api/rooms?floor_id=X` | Alpine.js fetches on select change; endpoints reused by M09 movement wizard |
| Photo storage path | `storage/app/public/assets/{asset_id}/photos/` | Standard Laravel public disk; symlink already in place from M00 |
| Attachment preview | Download link only (no inline preview) | Inline PDF/image preview is a M07 UI enhancement; not needed for MVP |
| File size limits | Photos: 10 MB max. Attachments: 20 MB max | Enforced in controller validation; shared hosting safe |
| `company_id` mutability | Read-only after asset creation for standard users | Only changes via approved inter-company transfer (M09); prevents accidental re-assignment |

### M03 wire-up required (M02 placeholder) — resolved 2026-08-09

> `CompanyController::toggleActive()` had a comment placeholder: `// When M03 adds assets table, add: if ($company->assets()->exists()) { ... }`. M03 shipped the `assets` table and `Asset::company()` relation without wiring the inverse guard in.
>
> **Closed as part of M02's completion pass.** Added `Company::assets(): HasMany`; both `CompanyController::toggleActive()` and `destroy()` now block deactivation with a flash error (`"Cannot deactivate: N asset(s) are still assigned to this company."`) when `$company->assets()->count() > 0`. `Asset` uses `SoftDeletes`, so the count naturally excludes soft-deleted/disposed assets. Regression tests in `tests/Feature/Admin/CompanyTest.php`.

---

## Outstanding gaps in "done" modules

M01's four gaps (user admin UI, role admin UI, org master CRUD, activity log) were closed 2026-08-09 — see "M01 — completion decisions" below. M02's sole gap (company deactivation guard) was closed the same day — see directly above. No known gaps remain in any module marked ✅ done.

**Impact on M08 (resolved):** approver steps route by Spatie role *name*; roles are now assignable through `/admin/users` and `/admin/roles` instead of `tinker` only. The rename-cascade in `RoleController::update()` keeps `approval_steps.approver_role` in sync when a custom role is renamed.

---

## M01 — User & Access: completion decisions (2026-08-09)

Full plan: `docs/planning/modules/M01-implementation-plan.md`. Four scope decisions were confirmed with the user before implementation (see that doc's "Scope decisions" table) — designations built fully, org masters reuse `MasterController`'s registry, roles get full CRUD with guards, and activity log gets a global viewer. Summary of what shipped:

| Decision | Choice | Why |
|----------|--------|-----|
| Org masters (departments/branches/designations) | Added to `MasterController::ENTITIES` with their own `permission` key (`departments.manage` etc.) and a `usage` guard checked in `destroy()` | Reusing the existing registry gets 3 admin screens for ~6 lines instead of 3 near-identical controllers; the `usage` guard stops a hard-delete from silently nulling `users`/`assets` FKs |
| Custom `App\Models\Role` | Extends Spatie's `Role`, adds `SEEDED`/`LOCKED` constants, `PERMISSION_GROUPS` matrix map, and `LogsActivity` | Needed a home for role-editor metadata and audit logging; registered via `config/permission.php` so all three existing consumers (`WorkflowController`, `WorkflowService`, `RolePermissionSeeder`) get it for free |
| Role logging is manual | `syncRoles`/`syncPermissions` are pivot writes and fire no Eloquent events — `LogsActivity` alone never sees them. Both `UserController` and `RoleController` call the `activity()` helper explicitly after every role/permission change | Verified in code before build; the alternative (assuming the trait "just works") would have shipped a silent audit gap |
| `User::getActivitylogOptions()` uses `logOnly()`, not `logFillable()` | `password` is in `$fillable`; `LogsActivity` does not consult `$hidden`. An explicit allow-list (`name`, `email`, `is_active`, `mfa_enabled`, `must_change_password`, the 3 org FKs) plus `dontSubmitEmptyLogs()` | `logFillable()` would have written password hashes into `activity_log` on every password change and forced-reset. Regression-tested in `UserTest::test_activity_log_never_records_the_password_hash` |
| Role rename cascades instead of blocking | Renaming a custom role referenced by `approval_steps.approver_role` retargets those steps inside the same DB transaction and flashes the count | `approval_steps` stores a role *name*, not an FK. Blocking the rename would make a referenced custom role permanently unrenamable; seeded role names are already frozen by the "cannot rename" guard so this only ever touches custom roles |
| Super Admin permission set is immutable | `RoleController::update()` ignores the submitted `permissions[]` array for the role named `Super Admin` and forces `Permission::all()` | Prevents an admin from locking themselves (or everyone) out by accidentally stripping `roles.manage` from the one role guaranteed to have it |
| `RolePermissionSeeder` is now install-only | Documented in the seeder's docblock and here: `syncPermissions()` is destructive, so running `db:seed` on a live install silently reverts every permission edit made through the Roles UI | Before M01 shipped, `db:seed` was harmless to re-run since nothing else wrote to `role_has_permissions`. That stopped being true the moment the Roles screen shipped |
| Two pre-existing bugs fixed alongside | Sidebar Administration group widened from `masters.manage` to also include `masters.view` (fixes Auditor never seeing its own Shared Masters link) and the other relevant permissions; `admin.login-history.index` got its missing nav entry | Both were found while auditing the sidebar for the new Users/Roles/Activity Log links — fixing them here was strictly cheaper than a follow-up module |

---

## M08 — Approval Workflow

### Pre-implementation decisions (confirmed 2026-08-07)

| Decision | Choice | Why |
|----------|--------|-----|
| P8.1 — Escalation authority | **Escalation widens, never skips** — after a step escalates, BOTH the original approver and the escalation target may act; whoever acts first resolves the step | Escalation is about unblocking a stalled step, not removing the assigned approver's authority |
| P8.2 — Reject → resubmit | **Rejection is terminal** for that `approval_requests` row; no edit-in-place. The consumer calls `submit()` again, creating a fresh row | Keeps `approval_actions` a clean per-attempt log; a re-opened request would make "who approved what" ambiguous |
| Notifications with M12 unbuilt | Minimal `NotificationService::send($user, $type, $data)` now, database channel only, via `App\Notifications\GenericNotification` | The `notifications` table and the sidebar bell already existed but were never fed; M12 extends channels behind the same signature |
| Approver routing | **Role-based only for MVP** — no department-head field exists anywhere (no `department_id` on `User`, no `head_user_id` on `Department`) | The spec's "Dept Head" step is approximated with the existing "Approver" role; swap to `approver_type=user` once dept heads exist |

### Implementation choices (made during build, 2026-08-07)

| Decision | Choice | Why |
|----------|--------|-----|
| Domain hand-off mechanism | `ApprovalRequestApproved` event on terminal approval only — M08 never calls `TagService`/`MovementService` directly | No consumer module exists yet; consumers listen and switch on `$event->request->workflow->module`. Fired synchronously so listeners run in the same request cycle |
| One active workflow per module | `submit()` throws on 0 or >1 active workflows; `WorkflowService::activate()` deactivates module siblings so the admin UI maintains the invariant | Silently picking one of several active workflows would make approvals nondeterministic |
| `approval_actions` shape | Append-only, `created_at` only (`$timestamps = false`), copying `LoginHistory` | `created_at` *is* the act timestamp — a separate `acted_at` column would duplicate it |
| "Already escalated" state | Derived from the existence of an `escalate` action row for `(request_id, step_level)` — no boolean flag | The append-only log is already the source of truth; a flag could drift from it. Also what makes `escalate()` idempotent |
| Step ordering | `nextStepAfter()` walks to the next-highest level, not `level + 1` | Levels are only unique-per-workflow, not contiguous; a 1/3/7 chain still advances correctly |
| Escalation due check | SQL join + `whereNotExists` filters candidates; the `started_at + escalation_hours < now()` arithmetic runs in PHP | Adding a *column* number of hours to a timestamp has no portable form across MySQL and the SQLite test suite |
| Inbox eligibility filter | `canAct()` applied in PHP over pending rows, not expressed in SQL | Eligibility depends on per-row escalation state; a pure-SQL version roughly doubles query complexity for a feature with zero live consumers. **Deliberate deferral — revisit when M09/M13 produce real volume** |
| Post-resolution visibility | `ApprovalRequestPolicy::view()` also admits anyone who recorded an action, plus `workflow.manage` holders | `canAct()` goes false the moment a request resolves; without this an approver loses the audit trail they contributed to (found during browser smoke test) |
| Route param name | `{approval_request}`, not `{request}` | `{request}` would shadow the `Illuminate\Http\Request $request` that approve/reject also need |

### M08 companion fix (RolePermissionSeeder)

> `Asset Manager` did not hold `workflow.approve`, but both seeded default workflows route a step to it — those users would have matched a step and then been 403'd. Added to the role's `syncPermissions([...])`.

### M08 wire-up required (later modules)

> `disposal` and `kit_assignment` deliberately have **no** seeded workflow — M13/M17 (or an admin via `/admin/workflows`) configure them, which is the proof that "configurable without code changes" holds.
> M05/M09/M13/M17 each add a listener for `ApprovalRequestApproved` filtered on `workflow->module`.

---

## Pending Decisions (must answer before each module starts)

### M04 — Dynamic Fields

| # | Question | Status |
|---|----------|--------|
| P4.1 | Field builder UI location: inline on category edit page, or separate `/admin/categories/{cat}/fields` dedicated page? | ❓ Pending |
| P4.2 | `resolveForCategory()` cache strategy: per-request only (request-scoped singleton), or cache to file/DB per category code version? | ❓ Pending |

### M05 — QR / Barcode Tags

| # | Question | Status |
|---|----------|--------|
| P5.1 | Tag number format: what is the pattern? (e.g. `AW-000001`, `{PREFIX}-{6-digit}`, fully custom?) | ❓ Pending |
| P5.2 | Print label layout: grid of labels on A4 sheet, or individual label per page? What label size? (e.g. 50×25mm, 63×38mm) | ❓ Pending |

### M06 — Bulk Import / Export

| # | Question | Status |
|---|----------|--------|
| P6.1 | Import processing: synchronous (inline, blocks request) or queued job (background, shows progress page)? Sync is simpler; queue is needed for large files. | ❓ Pending |
| P6.2 | Maximum rows per import batch? (Practical limit to prevent memory issues on shared hosting) | ❓ Pending |

### M08 — Approval Workflow

| # | Question | Status |
|---|----------|--------|
| P8.1 | When a step is escalated, can the original approver still act, or do they lose the ability? | ✅ Resolved 2026-08-07 — see M08 section above |
| P8.2 | After rejection, can the requester edit and resubmit the same record, or must they create a new request? | ✅ Resolved 2026-08-07 — see M08 section above |

### M09 — Asset Movement

| # | Question | Status |
|---|----------|--------|
| P9.1 | Bulk movement (multiple assets, non-kit): one approval request for the whole batch, or one per asset? Master plan notes "TBD: default per batch" — needs confirmation. | ✅ Resolved 2026-08-12 — **one approval per batch**, confirmed with the user before build. See M09 section below. |

### M09 — Asset Movement: implementation decisions (2026-08-12)

| Decision | Choice | Why |
|----------|--------|-----|
| P9.1 — bulk approval granularity | **One approval per batch.** New `asset_movement_batches` table is the approvable for a multi-select bulk move; it groups N `asset_movements` rows via `batch_id` | User's explicit call. Matches the master plan's stated default and keeps the approver inbox to one entry per bulk action instead of N |
| `asset_movement_batches` doubles as M17's future kit-assignment grouping | `kit_assignment_id` (nullable, unconstrained — no `kit_assignments` table yet) lives on the batch, denormalized down onto each `asset_movements` row at apply time | A17.11's "kit assignment approval mode: single vs per_asset" is the same shape as P9.1's bulk-vs-per-asset question. Building one grouping model now means M17 calls `MovementService::applyBulk()` directly instead of inventing a second batch table |
| No `draft` / `approved` movement status | Enum is `pending_approval` \| `rejected` \| `completed` only | Neither wizard has a save-for-later step, and `apply()`/`applyBulk()` run synchronously inside the `ApprovalRequestApproved` listener — a row never rests in an "approved" state, it goes straight to `completed` |
| Movement-type-code-driven `applyToAsset()` | `RETURN` explicitly clears `custodian_id`; it does not read `to_custodian_id` | A generic "if a to_* value is present, apply it" would silently no-op a Return (nothing was submitted to clear the custodian *to*), leaving the asset still assigned |
| Reused existing `movement.assign` / `movement.transfer` / `movement.verify` permissions | No new permissions added. `movement.assign` gates Assignment/Return/Custodian Change, `movement.transfer` gates Transfer/Inter-Company Transfer. `assets.bulk` (already seeded for M06 export) gates the new multi-select bar | `RolePermissionSeeder` already seeded and assigned these three `movement.*` permissions ahead of M09 (visible per-role split: Department User only got `movement.assign`, Asset Manager got all three) — that shape was clearly anticipating exactly this assign/transfer split, so building `movement.create`/`movement.intercompany` as the module doc originally sketched would have duplicated it |
| `MarkAssetMovementRejected` listener (new, not in the original sketch) | Second listener on `ApprovalRequestRejected`, flips `asset_movements.status` / `asset_movement_batches.status` to `rejected` | M05's `TagReplacementRequest` deliberately leaves its own status untouched on rejection because nothing else reads it. M09's "asset already has a pending movement" guard *does* read `asset_movements.status` directly — without this listener a rejected movement would permanently block the asset from any future movement |
| No separate `GET /assets/{asset}/movements` route | Per-asset history is a tab on `assets.show`, backed by eager-loaded `$asset->movements` | Matches the existing Tags and History tabs, neither of which has its own route either — avoids a redundant page for data already shown in context |
| Optional "New Status" field + `asset_status_histories` | Movement wizard has an optional target-status dropdown; if set, `applyToAsset()` writes `asset_status_histories` and updates `assets.status_id` | The module doc lists `asset_status_histories` as a real table and "append status history" as a task, but no movement type change implies a specific status transition in the BRD/spec — inferring one (e.g. Assignment always flips Available→Assigned) would be a state-machine invention with no request behind it. An explicit, optional field satisfies both the table and the task without guessing |

### M13 — Disposal & Scrap: implementation decisions (2026-08-13)

| Decision | Choice | Why |
|----------|--------|-----|
| No seeded `disposal` workflow, still | Left unseeded, as M08 originally decided. Tests build a single-step `ApprovalWorkflow` + `ApprovalStep` inline instead of using `WorkflowSeeder` | The M08 decision log already states this is deliberate proof that workflows are configurable without code; M13 shipping doesn't change that — an admin (or a future seeder change) configures it via `/admin/workflows` when the org is ready |
| Approval does not perform the write-off | `MarkDisposalApproved` only flips `disposal_requests.status` to `approved`; write-off and scrap are separate manual actions gated by `disposal.complete` | The module doc's task list (enable write-off action → write-off with financial fields → scrap complete) describes three distinct human steps, not one automatic apply — different from M09 where `apply()` runs synchronously because there's nothing left to decide once approved |
| `disposal_requests.status` transaction safety | `DisposalService::submit()` wraps row creation + `WorkflowService::submit()` in `DB::transaction()` | Because `disposal` has no seeded workflow, a submit attempt before an admin configures one is a realistic, not just theoretical, failure path — without the transaction it would leave an orphaned `pending_approval` row that permanently blocks the asset via `hasPendingDisposal()`. The same fix was retrofitted onto `MovementService::submit()` (`transfer` just happens to always be seeded, so the gap was latent but real there too — an admin can deactivate a workflow via `/admin/workflows`) |
| Cross-module movement/disposal guard | `MovementService::assertMovable()` now also rejects `Asset::hasPendingDisposal()`; `DisposalService::assertDisposable()` rejects `Asset::hasPendingMovement()` | The BRD requires "disposed assets cannot move," but an asset mid-disposal (approved or written-off, not yet scrapped) isn't disposed *yet* — without this mutual guard a movement could complete while a disposal was awaiting write-off, or vice versa, leaving inconsistent from/to state |
| Reused `asset_status_histories` (M09's table) for scrap completion | `DisposalService::scrap()` writes to the same ledger table M09 introduced, rather than a disposal-specific history table | M09's decisions-log entry for that table predicted exactly this: "a cross-module append-only ledger... M11/M13 write to it too later" |
| No disposal-specific attachment table | The task list's "disposal request form with attachments" is satisfied by the asset's existing Attachments tab (M03), not a new `disposal_attachments` table | Avoids duplicating an already-solved upload/storage/permission mechanism for the same underlying asset; a disposal-specific note directs users to the existing tab |
| Reused `disposal.request` / `disposal.approve`; added `disposal.complete` | No new permission for submission or viewing — `disposal.approve` (seeded onto Approver, previously unused by any code) now gates broad index visibility; `disposal.complete` (new) gates write-off/scrap | `RolePermissionSeeder` had already seeded `disposal.approve` for the Approver role ahead of M13 but nothing consumed it (the workflow engine's `canAct()` is role-based, not permission-based) — using it for index visibility puts it to work instead of leaving it dead, and matches the same "seeded ahead of time, shape anticipated the module" pattern M09 found with `movement.assign`/`movement.transfer` |

---

## M10 — Audit & Verification: implementation decisions (2026-08-15)

| Decision | Choice | Why |
|----------|--------|-----|
| Per-campaign auditor assignment | New `audit_campaign_auditors` pivot; only assigned auditors (or anyone holding `audit.manage`) see a campaign on the verify worklist or can act on its items | The module spec's table list has no auditor pivot, but UF-11 step 2 is explicitly "Assign auditors" and step 7 notifies them specifically — a pivot was the smallest structure that satisfies both without inventing a broader eligibility model |
| No approval workflow | `AuditService` is plain `audit.manage`/`audit.verify`-gated CRUD, like M11 | M10 depends only on M03/M05 per the module map; there is no "who approves an audit" concept in the BRD, unlike M09/M13 which gate on approval before applying anything |
| Missing/damaged findings never touch `assets.status_id` | Recorded on `audit_items` (and the compliance report) only | Keeps M10 free of the M09/M11/M13 status-ledger coupling — an audit finding is evidence, not an action; the Asset Manager decides what to do about a missing/damaged asset via the existing movement/maintenance/disposal flows |
| Scan integration extends `ScanController::resolve()`, doesn't replace it | An `assigned`-tag scan by a user holding `audit.verify` with a pending item on an active assigned campaign redirects into `audits.verify` instead of `assets.show`; every other outcome (available/inactive tags, users without `audit.verify`, assets with no open item) is unchanged | Satisfies "QR scan marks correct audit item verified" without touching the `/scan/{tag_number}` URL contract M05 exposes to M08 and M10 alike |
| Compliance report ships in two places | A per-campaign `/audits/campaigns/{id}/report` (inline Excel/PDF, reusing `ReportPdfExporter`) *and* a new enabled `audit_campaign` entry in M14's `ReportRegistry` for the cross-campaign filtered view, both backed by the same `AuditCampaignExport` + `ReportService::buildAuditCampaignQuery()` | M14 already promises "exports match on-screen filters" as an invariant (`ReportService` docblock); building two independent export paths would have broken that by construction. One export class serves both entry points, so M14's existing >500-row queueing behaviour is inherited for free by the registry path while the campaign-scoped path stays inline (campaigns are bounded by definition) |
| `audit.manage` gates the campaign report, not `reports.export` | `AuditReportController` checks `audit.manage` only | The seeded Auditor role holds `audit.manage` + `reports.view` but not `reports.export`; an auditor must be able to pull the compliance report for a campaign they ran without also being granted the broader cross-module export permission |
| Expected location/custodian snapshotted at activation | `audit_items.expected_location_id` / `expected_custodian_id` copied from the asset at the moment items are generated, not read live from `assets` at verify/report time | The auditor needs to see what the register *claimed* when the campaign started, and the report needs to show that even after the asset is later moved — same "capture now, read later" pattern as M11's `maintenance_records.previous_status_id` |

---

## Integration pass — decisions made while fixing browser-only defects (2026-08-13)

Full write-up: [`integration-testing.md`](integration-testing.md). Six defects were found
against a 390/390-green suite; these are the judgement calls made while fixing them.

| Decision | Choice | Why |
|----------|--------|-----|
| Fix the seed, not the approval engine, when no one could approve anything | `AdminUserSeeder` now grants `Super Admin` + `Asset Manager` + `Approver`, rather than making `workflow.manage` a universal override in `canAct()` | Approver eligibility is role-matched by design (M08/P8.1), and an org may legitimately want Super Admin ≠ Approver. The engine was correct; the seed data was internally inconsistent with `WorkflowSeeder` |
| `@js()` over `@json()` in Alpine attributes | Both movement wizards now use `@js(...)` | `@json` splits its expression on commas and reassigns the flags argument, silently dropping `JSON_HEX_QUOT`. `@js` passes the whole expression to `Js::from()` and always applies the required flags. Recorded in CLAUDE.md as a codebase-wide rule |
| Show bulk batch members in Movement History rather than building a batch row | Dropped `whereNull('batch_id')`, added a "Bulk" badge per member | Every row in "Movement History" should be one asset actually moving. The batch-row design implied by the original comment was never built, so bulk submissions were invisible; one row per asset movement is both simpler and the more accurate reading of the screen's purpose |
| Wrap submit-then-open-approval in a transaction | `DisposalService::submit()` and `MovementService::submit()` | A failed `WorkflowService::submit()` otherwise leaves an orphaned `pending_approval` row that permanently trips `hasPendingMovement()` / `hasPendingDisposal()`, locking the asset out of all future movement and disposal. Not hypothetical for `disposal`, which ships with no seeded workflow |
| Drop `x-transition` from the bulk action bar | Plain `x-show` + `x-cloak` | Verified in-browser that Alpine's JS transition applied a stale state to this fixed-position element (bar stayed hidden while assets were selected, then appeared once cleared). The bar is a utility affordance; correctness beats a 150ms fade |
| Regression tests assert on rendered HTML, not just DB state | `MovementViewRegressionTest` checks for `JSON.parse(` and the absence of `typeCodes: {"` | The `@json` defect was invisible to every existing test because they POST to endpoints and assert on the database. Asserting on markup is the cheapest way to catch this class without adding a headless-browser layer |

**Acknowledged gap:** `x-transition` behaviour and Alpine plugin registration are not
observable from phpunit. Covering them properly needs Dusk or Playwright — currently the
largest hole in this project's testing strategy.

---

## Cross-module Contracts (must not break)

These are interfaces between modules. Changing them requires coordinating both sides.

| Contract | Owner | Consumers | Notes |
|----------|-------|-----------|-------|
| `DynamicFieldService::resolveForCategory($id)` | M04 | M03 forms, M06 import, M14 reports | Must be stable before M06 starts |
| `Asset::hasCustomFieldData()` | M03 (stub → M04 real) | M04 category lock | M03 returns `false`; M04 replaces with real check |
| `WorkflowService::submit/approve/reject` | M08 | M09, M05 replacement, M17 kits | M08 must ship before M09 can go live |
| `NotificationService::send($user, $type, $data)` | M12 | M08, M09, M10, M11, M13 | Stub in M07; full implementation in M12 |
| `TagService` + `/scan/{tag_number}` route | M05 | M08 tag replacement, M10 audit scan | Scan URL format is a contract — never change `tag_number` slug |
| `MovementService::applyBulk()` | M09 | M17 kit assignment | Must accept `Collection $assets` + `KitAssignment` |
| `company_id` on `assets` | M03 | M09 inter-company transfer, M06 import, M14 reports | Updated atomically on inter-company transfer approval; never directly writeable after create |
