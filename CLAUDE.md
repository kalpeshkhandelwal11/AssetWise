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
| M05 QR / Barcode | 🔄 next | Unblocked — Phase 1 (pool/assign/scan) needs only M03; the replacement flow can now use M08 |
| M06 Bulk Import / Export | 🔄 next | Unblocked — M03 + M04 both done |
| M07, M09–M17 | ⏳ pending | See `docs/planning/MODULES_INDEX.md` |

Test suite: **307 passing**. For the full developer setup guide see `docs/developer-setup.md`.

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
| `MovementService::applyBulk()` | M09 | M17 kit assignment |

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

Default roles seeded: Super Admin, Asset Manager, Department User, Auditor, Approver, Viewer. **These 6 seeded roles cannot be renamed or deleted** (`App\Models\Role::SEEDED`); Super Admin's permission set is additionally immutable — `RoleController` always forces it back to every permission, regardless of what's submitted. Custom roles created through `/admin/roles` have full CRUD, including delete, as long as they're not referenced by `approval_steps.approver_role` or held by any user.

## PWA Scope

The **entire web app** is the PWA (not a scan-only mini-app). `vite-plugin-pwa` generates a service worker that caches static assets only; all Blade pages and API calls use network-first strategy. Creating/editing records requires connectivity. HTTPS is required in production for service worker and camera access (`html5-qrcode` for QR scanning within audit/movement flows).

## Deployment (Shared Hosting)

```bash
php artisan migrate --force
php artisan db:seed
php artisan storage:link
# Cron: * * * * * php /path/to/artisan schedule:run
```

Point web root to `/public`. Set `APP_DEBUG=false` in production `.env`.
