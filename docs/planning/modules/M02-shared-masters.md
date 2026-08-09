# M02 — Shared Masters

| | |
|--|--|
| **Developer** | Dev 1 |
| **Phase** | 1 |
| **Depends on** | M00, M01 |
| **Blocks** | M03 |

## Scope

CRUD for all shared lookup tables used across asset lifecycle, including the **Companies master** that drives multi-company asset ownership and inter-company transfers.

## DB Tables

- `companies` (name, code unique, address, contact, is_active) — **owns assets; drives inter-company transfers**
- `asset_statuses` (name, code, color, is_system, is_active)
- `asset_types` (name, code — independent from category)
- `locations` → `buildings` → `floors` → `rooms` (hierarchical FKs)
- `priorities`, `movement_types`, `audit_types`, `disposal_types`, `maintenance_types`

## Routes

`/admin/masters/{entity}` — reuse generic master CRUD pattern or one controller per entity.

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/admin/companies` | CompanyController |
| CRUD | `/admin/masters/{entity}` | Generic MasterController |

## Tasks

- [x] Migrations + models for all tables
- [x] **Companies CRUD** — name, code (unique slug), address, contact info, active flag
- [x] Reusable master list component (search, sort, paginate)
- [x] Reusable master form component
- [x] Location cascade API endpoints for Alpine (location → building → floor → room)
- [x] Seed defaults: at least one default company, statuses (Available, Assigned, In Maintenance, Disposed), movement types (including **Inter-Company Transfer**), etc.
- [x] **Soft-deactivate with "in use" validation (block deactivating a company that owns active assets)** — `Company::assets()` relation added; `CompanyController::toggleActive()` and `destroy()` both block with a flash error when the company still owns assets
- [x] Permissions: `masters.view`, `masters.manage`, `companies.manage`

## Acceptance criteria

- All masters CRUDable by Asset Manager / Super Admin
- Companies list/create/edit/deactivate works; cannot deactivate if assets exist under it
- Location hierarchy cascades correctly in UI
- System statuses cannot be deleted
- Seeded data (including at least one company) available after `db:seed`

## Parallel note

Can overlap with Dev 2 starting M08 after M01 completes.
