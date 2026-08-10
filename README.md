# AssetWise

Enterprise Asset Management System for a single organization.

**Stack:** Laravel 13, MySQL 8, Blade, Alpine.js, Tailwind CSS, PWA

## Status

| Module | Status |
|--------|--------|
| M00 Foundation | ✅ done |
| M01 User & Access (auth, RBAC, users/roles/org-master admin UI) | ✅ done |
| M02 Shared Masters (companies, locations, lookups) | ✅ done |
| M03 Asset Master (categories, assets, photos, attachments) | ✅ done |
| M04 Dynamic Fields (per-category EAV) | ✅ done |
| M05 QR / Barcode (tag pool, generate/assign/scan, label sheets) | ✅ done |
| M06 Bulk Import / Export (per-category template, queued jobs) | ✅ done |
| M07 Shared UI Components (`x-data-table`, `x-filter-bar`, `x-status-badge`, …) | ✅ done |
| M08 Approval Workflow (multi-level, escalation, inbox) | ✅ done |
| M09–M17 | ⏳ pending |

Test suite: **372 passing**. See [`docs/planning/MODULES_INDEX.md`](docs/planning/MODULES_INDEX.md) for the full dependency graph and [`docs/decisions-log.md`](docs/decisions-log.md) for the running record of decisions.

## Planning

All implementation planning lives in [`docs/planning/`](docs/planning/):

- [Planning index](docs/planning/README.md)
- [Master plan](docs/planning/MASTER_PLAN.md)
- [Module index (parallel development)](docs/planning/MODULES_INDEX.md)
- [BRD v1](docs/planning/Asset_Management_BRD_v1.txt)

## Development

Full walkthrough: [`docs/developer-setup.md`](docs/developer-setup.md).

1. Install [Laragon](https://laragon.org/download/) — **PHP 8.3+ is required** (Laravel 13 will not boot on 8.2), plus MySQL 8, Composer, Node 20 LTS
2. `composer install && npm install`
3. Copy `.env.example` to `.env`, `php artisan key:generate`, create the `assetwise` database
4. `php artisan migrate --seed && php artisan storage:link && npm run build`
5. Log in at `http://assetwise.test` as `admin@assetwise.test` / `Admin@1234`

```powershell
php artisan test    # 372 tests, in-memory SQLite — never touches your dev database
```

## Repository

https://github.com/kalpeshkhandelwal11/AssetWise
