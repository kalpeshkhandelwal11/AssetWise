# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AssetWise is a **greenfield** Enterprise Asset Management System. It is a **single-schema system** (not multi-tenant), but each asset carries a `company_id` so ownership is tracked per company and assets can be transferred between companies via an approval-gated **inter-company transfer** flow. Reports can be filtered and grouped by company.

**Module status:**

| Module | Status | Notes |
|--------|--------|-------|
| M00 Foundation | ✅ done | Laravel 13 scaffold, packages, base UI |
| M01 Auth & RBAC | ✅ done | Session lifetime, force-change, login history, seeded RBAC, plus full user/role/org-master admin UI (`UserController`, `RoleController`, `/admin/users`, `/admin/roles`) and a global activity log viewer |
| M02 Shared Masters | ✅ done | Companies, statuses, locations, masters CRUD, including the "block deactivating a company that owns assets" guard in `CompanyController` |
| M03 Asset Master | ✅ done | Categories, assets CRUD, photos, attachments |
| M04 Dynamic Fields | ✅ done | Category-scoped EAV fields with inheritance/override |
| M08 Approval Workflow | ✅ done | Polymorphic multi-level engine, escalation, approver inbox |
| M05 QR / Barcode | ✅ done | Tag pool (`settings`, `tag_batches`, `tags`, `asset_tag_assignments`, `scan_logs`), generate/assign/scan, PDF + Word label sheets, Phase 2 replacement via `WorkflowService` + `ApplyTagReplacement` listener on `ApprovalRequestApproved` |
| M06 Bulk Import / Export | ✅ done | Per-category Excel template (`AssetTemplateExport`), row-validated import (`AssetImport`, `import_batches`/`import_batch_rows`), filtered export (`AssetExport`), first `ShouldQueue` jobs in the codebase (`ProcessAssetImport`, `GenerateAssetExport`) |
| M07 Shared UI & Services | ✅ done | Reusable Blade components (`x-data-table`, `x-filter-bar`, `x-status-badge`, `x-breadcrumb`, `x-confirm-modal`) retrofitted across all M01–M06 list/filter screens, eliminating duplicated table/filter markup; service interfaces (`WorkflowService`, `NotificationService`) already documented via M08 |
| M09 Asset Movement | ✅ done | Assignment/Return/Transfer/Custodian Change/Inter-Company Transfer, all approval-gated via `MovementService` + `ApplyAssetMovement`/`MarkAssetMovementRejected` listeners on `ApprovalRequestApproved`/`Rejected`. Bulk multi-select gets one approval per batch (`AssetMovementBatch`, resolved P9.1) — the same model M17 will reuse for kit assignments |
| M11 Maintenance | ✅ done | `MaintenanceService`/`AmcService`/`WarrantyService`/`ExpiryAlertService`, all plain `maintenance.manage`-gated CRUD — unlike M09/M13 there is **no approval workflow** here (M11 depends only on M03). Starting an in-progress maintenance record flips the asset to the `MAINTENANCE` status (capturing `previous_status_id` on the record) and restores it on completion/cancellation, same `AssetStatusHistory` ledger pattern as M13's `scrap()`. `amc_contracts`/`warranty_records` support full multi-record history per asset; `assets.amc_expiry`/`warranty_expiry` stay synced to the furthest `end_date`. `alerts:expiry` (daily scheduled command, mirrors `approvals:escalate`) fires exactly at the 30/7/1-day thresholds, notifying `maintenance.manage` users + the asset's custodian via `NotificationService`. Also adds `assets.is_eol`/`eol_projected_date` |
| M13 Disposal & Scrap | ✅ done | `disposal_requests` lifecycle (pending → approved → written_off → scrapped) via `DisposalService` + `MarkDisposalApproved`/`MarkDisposalRejected` listeners; approval only unlocks write-off, it doesn't apply anything. Scrap sets `assets.status_id` to Disposed and appends to M09's `asset_status_histories` ledger. `disposal` still has no seeded workflow (deliberate, M08) |
| M14 Reports & Dashboard | ✅ done | Company-scoped dashboard (`DashboardService`) plus 9 exportable reports (Asset Register, Movement, Inter-Company Transfer, Disposal, Asset Aging, Utilization, Audit/Compliance, Audit Campaign, Depreciation Schedule) via `ReportService` + `ReportController` (`Reports/` namespace) + `ReportRegistry`. Excel via Maatwebsite Excel, PDF via a shared `ReportPdfExporter`/generic table view, large exports queued through `GenerateReportExport` (mirrors M06's `GenerateAssetExport`) with `NotificationService` "export ready" notify. Kit Assignment History remains registered in `ReportRegistry` as a disabled "coming soon" entry pending M17. **M11 has since shipped, so the Maintenance Report entry could be promoted to enabled in a follow-up** |
| M10 Audit & Verification | ✅ done | `AuditService`, plain `audit.manage`/`audit.verify`-gated CRUD like M11 — no approval workflow (M10 depends only on M03/M05). Campaigns scope assets via a JSON filter set, `activate()` snapshots matching non-disposed assets into `audit_items` (capturing `expected_location_id`/`expected_custodian_id`), and a new `audit_campaign_auditors` pivot restricts who can verify a given campaign. Missing/damaged findings are recorded on the item only — they never mutate `assets.status_id`. `ScanController::resolve()` routes an `audit.verify` holder with a pending item into `audits.verify` instead of the asset detail page, extending rather than changing the `/scan/{tag_number}` contract. Compliance report ships twice off one `AuditCampaignExport`: inline per-campaign at `/audits/campaigns/{id}/report`, and as M14's enabled `audit_campaign` registry entry |
| M16 Depreciation | ✅ done | Category-default + per-asset override depreciation, **Companies Act Schedule II** straight-line with **daily proration** and 5% default residual. Strategy pattern (`DepreciationCalculatorInterface` + `StraightLineCalculator`, four inactive stubs seeded via `DepreciationMethodSeeder`). `DepreciationService` resolves settings (asset override → category default → none), generates the schedule, and posts **only completed months** via the idempotent `depreciation:post-monthly` command (daily, mirrors `alerts:expiry`). Settings changes are **approval-gated** through a new `depreciation` workflow module: `submitSettingChange()` opens a `depreciation_setting_requests` row + `WorkflowService::submit()`, and `ApplyDepreciationSettingChange` (on `ApprovalRequestApproved`) supersedes the prior active `asset_depreciation_settings` row and regenerates the schedule; `MarkDepreciationRequestRejected` unblocks resubmission. **Cross-module:** M13 write-off stops depreciation and records `gain_loss = disposal_value − net book value` on `disposal_requests`; M09 inter-company transfer treats companies as separate legal entities — `resetForTransfer()` stops the seller's schedule and starts a fresh one for the receiver at net book value over the remaining life; M11 maintenance can be flagged capitalized (amount + extra life) which routes a capitalization request through the same depreciation approval. M14's Depreciation Schedule report is now enabled (`DepreciationReportExport` + `buildDepreciationScheduleQuery`) |
| M15 PWA | ✅ done | Installable full-site PWA. **The service worker is served by Laravel at `/sw.js`** (`PwaController`), not statically — `vite-plugin-pwa` writes it into `public/build/`, where its scope would be `/build/` and it would control no navigation. The manifest is generated from a new `config/pwa.php` at `/manifest.webmanifest` rather than emitted by the plugin (one source of truth, testable without a build). **Authenticated HTML is never cached** — navigations are network-only with an `/offline` fallback, a deliberate tightening of the "network-first for pages" wording below, since a cached page on a shared device leaks to the next user. Adds the first scan entry point in the UI: `GET /scan` plus `<x-qr-scanner>` (lazy-imported `html5-qrcode` via `Alpine.data('qrScanner')`) embedded in M10's verify worklist; both navigate to `scan.resolve` and rely on M10's existing auditor branch. Also `<x-install-prompt>`, `public/icons/`, and a 375px responsive pass |
| M12, M17 | ⏳ pending | See `docs/planning/MODULES_INDEX.md` |

Test suite: **528 passing**. For the full developer setup guide see `docs/developer-setup.md`; for the end-to-end browser integration pass (and the six defects it caught that the suite could not) see `docs/integration-testing.md`.

## Blade + Alpine gotchas (learned the hard way — see `docs/integration-testing.md`)

- **Never use `@json(...)` with a comma in the expression inside an HTML attribute.** Blade compiles `@json` via `explode(',', $expression)` and treats everything after the first comma as the *flags* argument, so `@json($x->pluck('code', 'id'))` silently drops `JSON_HEX_QUOT` and emits raw `"` that terminates the attribute — killing the whole Alpine component. Use **`@js(...)`** for anything embedded in `x-data` / `@click`. (`{{ json_encode(...) }}` is also safe, since `e()` escapes the quotes.)
- **Vite never sees JavaScript written inside a Blade attribute.** A dynamic `import('some-package')` in an `x-data` expression reaches the browser as an unresolved bare specifier and throws. Anything needing bundler resolution (lazy imports, npm packages) must live in a module under `resources/js/` and be registered with `Alpine.data(...)` — see `qr-scanner.js` and `<x-qr-scanner>`.
- **Approver eligibility is matched on Spatie *role*, not permission** (`WorkflowService::matchesStep()`). Holding `Super Admin` does not make a user an approver for a step routed to `Approver`. Seeded users and seeded workflow steps must be kept consistent — `AdminUserSeeder` grants `Super Admin` + `Asset Manager` + `Approver` for exactly this reason.
- **`WorkflowService` reports configuration errors under the key `workflow`**, which matches no form field. Every form that calls `submit()` needs an explicit `@error('workflow')` block or the submission fails silently.
- Any service that creates a row and then calls `WorkflowService::submit()` must wrap both in `DB::transaction()` — otherwise a missing/deactivated workflow leaves an orphaned `pending_approval` row that permanently trips the `hasPendingMovement()` / `hasPendingDisposal()` guards.

**Stack:** Laravel 13, MySQL 8, Blade + Alpine.js + Tailwind CSS, PWA (vite-plugin-pwa)

**Key packages:**
- Laravel Breeze (Blade) — auth scaffold
- Spatie Laravel Permission — RBAC
- Spatie Activity Log — audit trail (before/after)
- simplesoftwareio/simple-qrcode + picqer/php-barcode-generator — QR/barcode generation
- Maatwebsite Laravel Excel + DomPDF — bulk import/export and PDF reports
- vite-plugin-pwa — full-site PWA (Phase 3)

## Local Development Setup (Laragon on Windows)

1. Install [Laragon Full](https://laragon.org/download/) with **PHP 8.3+** (hard requirement — Laravel 13; Composer's `platform_check.php` aborts on 8.2), MySQL 8, Composer, Node 20 LTS
2. Create database: `CREATE DATABASE assetwise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
3. Required PHP extensions: `mbstring`, `openssl`, `pdo_mysql`, `gd`, `intl`, `zip`, `exif`, `bcmath`
4. Site URL: `http://assetwise.test` (Laragon auto virtual host) or `http://localhost/AssetWise/public`

## Common Commands

```powershell
# Install dependencies
composer install
npm install

# Build assets
npm run dev        # development with hot reload
npm run build      # production build

# Database
php artisan migrate
php artisan migrate:fresh --seed   # reset + seed demo data
php artisan db:seed

# Storage
php artisan storage:link

# Queue (database driver — no Redis)
php artisan queue:work --stop-when-empty

# Scheduler (runs all scheduled jobs — registered in bootstrap/app.php's withSchedule)
php artisan schedule:run
php artisan schedule:list         # what is registered
php artisan approvals:escalate    # M08 escalation sweep — daily, idempotent, safe to run by hand

# Tests
php artisan test
php artisan test --filter=AssetTest   # single test class
php artisan test tests/Feature/Assets/  # single directory
```

## Testing Conventions

Tests use **in-memory SQLite** (`DB_DATABASE=:memory:`) so they never touch the dev database. Each test class gets fresh tables.

**Password policy:** `AppServiceProvider` sets `Password::defaults()` to min 8, mixed case, numbers, symbols. The factory default is `Admin@1234`. Always use this in tests:

```php
// Current password in tests (factory default)
$this->post('/login', ['email' => $user->email, 'password' => 'Admin@1234']);

// New password when testing update flows
'password' => 'NewPass@9876',   // also passes policy
```

**Session lifetime tests** — `_last_activity_at` is set by the middleware on the **first authenticated GET**, not on the login POST. Correct pattern:

```php
$this->post('/login', [...]);           // login
$this->get('/dashboard');               // seeds _last_activity_at
$this->travel(9)->hours();              // advance time
$this->get('/dashboard')->assertRedirect('/login'); // now expired
$this->travelBack();
```

**Event listeners** — `LogSuccessfulLogin` and `LogFailedLogin` are auto-discovered via their `handle()` type-hints. Do NOT add them to `AppServiceProvider::boot()` or they will fire twice per event.

**Approval tests** — prefer partial `Event::fake([SomeEvent::class])` over bare `Event::fake()`, which also swallows Eloquent model events that `LogsActivity` depends on. Escalation tests use `$this->travel(49)->hours()` then `$this->travelBack()`; `Asset` is the stand-in `approvable` since `WorkflowService` must stay model-agnostic.

**Role tests** — always include `use Tests\Concerns\SeedsRolesAndPermissions` and use `$this->createUserWithRole('Super Admin')` etc.

## Architecture

### Layering Convention

All modules follow this pattern — keep controllers thin:

```
app/Http/Controllers/{Module}/   — thin, delegate to services
app/Services/                    — all business logic lives here
app/Models/                      — Eloquent models + relationships
app/Policies/                    — authorization per module
app/Http/Requests/               — validation (including dynamic field rules)
app/Exports/ + app/Imports/      — Excel export/import classes
resources/views/layouts/         — app, guest, print (QR labels)
resources/views/components/      — x-data-table, x-filter-bar, x-dynamic-fields, x-attachment-uploader
resources/views/modules/         — per-module screens
routes/web.php                   — main routes
routes/api.php                   — scan endpoint + mobile helpers only
```

### Controller Namespacing

```
Admin/    — org masters, users, roles, RBAC settings, workflow config
Assets/   — asset CRUD, bulk, QR tags
Approvals/— approver inbox, approve/reject actions
Movement/ — Phase 2
Audit/    — Phase 2
Maintenance/ — Phase 2
Disposal/ — Phase 3
Reports/  — Phase 3
Api/      — minimal JSON for PWA scan endpoints
```

## Module System

Implementation is split into 17 modules with defined dependencies. See `docs/planning/MODULES_INDEX.md` for full dependency graph. Start order: M00 → M01 → (Dev1: M02→M03→M04→M05→M06 | Dev2: M08→M09→M11→M16→M17→M10→M13→M14→M15).

**Cross-module contracts that must not break:**

| Contract | Owner | Consumers |
|----------|-------|-----------|
| `DynamicFieldService::resolveForCategory($id)` | M04 | M03 forms, M06 import, M14 reports |
| `Asset::hasCustomFieldData()` | M03 | M04 category lock |
| `WorkflowService::submit/approve/reject` | M08 | M09, M13 |
| `ApprovalRequestApproved` event | M08 | M05, M09, M13, M17 — the *only* way M08 hands back to the domain |
| `NotificationService::send($user, $type, $data)` | M12 (stub built in M08) | M08, M09, M10, M11, M13 |
| `TagService` + `/scan/{tag_number}` route | M05 | M08 replacement, M10 audit scan |
| `MovementService::applyBulk(AssetMovementBatch $batch)` | M09 | M17 kit assignment — reuse `AssetMovementBatch` (set `kit_assignment_id`) rather than a new grouping table |

## Key Design Decisions

### Dynamic Fields (EAV)

Categories form a tree (`parent_id` on `asset_categories`). Fields defined on any ancestor are **inherited with override** — child categories can hide, relabel, or change the `required` flag via `category_field_overrides`. Never hard-code field resolution; always call `DynamicFieldService::resolveForCategory()`.

`asset_field_values` uses typed EAV columns (`value_text`, `value_number`, `value_date`, `value_boolean`) — not a single JSON blob — for searchability. Index exists on `(category_field_id, value_*)` for filtered asset lists.

### Multi-Company Asset Ownership

Each asset has a required `company_id` FK to the `companies` master. This is **not multi-tenancy** — all data lives in one schema. `company_id` reflects the current owning company and is updated atomically when an inter-company transfer approval completes. The `asset_movements` table stores `from_company_id` / `to_company_id` for inter-company transfer rows. Reports and dashboard filters accept a company scope.

### Category Lock

Once `asset_field_values` rows exist for an asset, `category_id` is **immutable** for standard users. Only users with `assets.override_category` can force a change; doing so does NOT migrate existing field values and logs a before/after activity entry.

### Custom Field Soft-Delete

Never hard-delete `category_fields` if any `asset_field_values` reference them. Soft-delete only. Deactivated fields are hidden on new asset forms but shown read-only on existing assets.

### QR/Barcode Tag Pool

Tags are pre-generated into a pool (`tags` table, status `available`), physically printed, then assigned to assets later. Assets are created without a tag by default. Tag replacement requires an approval workflow. Scan route `/scan/{tag_number}` resolves by status: `assigned` → asset detail, `available` → assign UI, `inactive` → retired message. Tag numbers are never reused.

### Approval Workflow

Every asset transfer, tag replacement, and disposal goes through multi-level approval (`approval_requests` + `approval_actions`, append-only). The workflow engine in M08 is polymorphic and supports escalation. Kit assignments are configurable: `single` (one approval for whole kit) or `per_asset` (controlled by `config/assetwise.php` key `kit_assignment_approval_mode`).

Consumers submit with `WorkflowService::submit($model, $module, $actor)` and **react to the `ApprovalRequestApproved` event** — M08 never calls domain services directly. That event fires only on the terminal step; listen and switch on `$event->request->workflow->module`, acting on `$event->request->approvable`. Escalation *widens* eligibility for the current step (original approver + next level both may act) rather than skipping a level. Rejection is terminal — resubmitting means calling `submit()` again for a fresh request. Exactly one workflow per module may be active; `WorkflowService::activate()` enforces it.

### Depreciation (Strategy Pattern)

`DepreciationCalculatorInterface` with `StraightLineCalculator` in MVP. Other methods seeded inactive in `depreciation_methods`. Category sets defaults; per-asset `asset_depreciation_settings` overrides. Monthly scheduler posts `depreciation_schedule_lines` and updates cached `current_book_value` on `asset_depreciation_settings`.

### Queue & Cache (Shared Hosting)

Use `QUEUE_CONNECTION=database` and `CACHE_DRIVER=database` (or file). No Redis assumed. Heavy exports run as queued jobs with download-link notification. Scheduler runs via a single cron entry: `* * * * * php artisan schedule:run`.

## RBAC Permissions (Seeded)

Permissions follow `{module}.{action}` naming, except org masters and most admin screens which use a single coarse `.manage` permission (matching every entity in `MasterController::ENTITIES`). Key non-obvious ones:
- `assets.override_category` — force category change when field data exists
- `category_fields.manage` — build/soft-delete dynamic field definitions
- `workflow.approve` — act on approval steps
- `imports.manage` — bulk upload per category
- `roles.manage` — full role CRUD + permission matrix at `/admin/roles`
- `departments.manage`, `branches.manage`, `designations.manage` — org master CRUD via `MasterController`'s registry (not dedicated controllers)
- `activity_log.view` — global audit trail viewer at `/admin/activity-log`
- `movement.assign` vs `movement.transfer` — split by movement type (Assignment/Return/Custodian Change vs Transfer/Inter-Company Transfer), not a single `movement.create`; see `MovementService::permissionFor()`
- `disposal.approve` — broad visibility on `/disposals` (not act-on-approval eligibility, which is role-based via `WorkflowService::canAct()`); `disposal.complete` gates write-off/scrap

Default roles seeded: Super Admin, Asset Manager, Department User, Auditor, Approver, Viewer. **These 6 seeded roles cannot be renamed or deleted** (`App\Models\Role::SEEDED`); Super Admin's permission set is additionally immutable — `RoleController` always forces it back to every permission, regardless of what's submitted. Custom roles created through `/admin/roles` have full CRUD, including delete, as long as they're not referenced by `approval_steps.approver_role` or held by any user.

## PWA Scope

The **entire web app** is the PWA (not a scan-only mini-app). The service worker (`resources/js/sw.js`, hand-written — `vite-plugin-pwa` runs in `injectManifest` mode and only substitutes the precache list) caches **static build assets and `/offline` only**. Creating/editing records requires connectivity. HTTPS is required in production for service worker and camera access (`html5-qrcode` for QR scanning within audit/movement flows).

**Pages are never cached.** Navigations are network-only with an `/offline` fallback — M15 tightened the original "network-first for pages" wording, because network-first writes each page to the cache and a permission-gated page would then be served to the next user of a shared floor device. If you touch the fetch handler, keep it that way.

Two things that will bite:

- `npm run build` is **required** for the PWA to exist at all — `/sw.js` proxies `public/build/sw.js` and returns a clean 404 without it. `public/build` is gitignored.
- Precache URLs are relative to `globDirectory`, so `vite.config.js` sets `modifyURLPrefix: { '': '/build/' }`. Without it a root-scoped worker resolves them to `/assets/…`, every entry 404s, and `Promise.allSettled` hides the failure — the cache silently stays empty.

## Deployment (Shared Hosting)

```bash
php artisan migrate --force
php artisan db:seed
php artisan storage:link
# Cron: * * * * * php /path/to/artisan schedule:run
```

Point web root to `/public`. Set `APP_DEBUG=false` in production `.env`.
