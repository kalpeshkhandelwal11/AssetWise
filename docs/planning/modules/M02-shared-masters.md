# M02 — Shared Masters

| | |
|--|--|
| **Developer** | Dev 1 |
| **Phase** | 1 |
| **Depends on** | M00, M01 |
| **Blocks** | M03 |

## Scope

CRUD for all shared lookup tables used across asset lifecycle.

## DB Tables

- `asset_statuses` (name, code, color, is_system, is_active)
- `asset_types` (name, code — independent from category)
- `locations` → `buildings` → `floors` → `rooms` (hierarchical FKs)
- `priorities`, `movement_types`, `audit_types`, `disposal_types`, `maintenance_types`

## Routes

`/admin/masters/{entity}` — reuse generic master CRUD pattern or one controller per entity.

## Tasks

- [ ] Migrations + models for all tables
- [ ] Reusable master list component (search, sort, paginate)
- [ ] Reusable master form component
- [ ] Location cascade API endpoints for Alpine (location → building → floor → room)
- [ ] Seed defaults: statuses (Available, Assigned, In Maintenance, Disposed), movement types, etc.
- [ ] Soft-deactivate with "in use" validation
- [ ] Permissions: `masters.view`, `masters.manage`

## Acceptance criteria

- All masters CRUDable by Asset Manager / Super Admin
- Location hierarchy cascades correctly in UI
- System statuses cannot be deleted
- Seeded data available after `db:seed`

## Parallel note

Can overlap with Dev 2 starting M08 after M01 completes.
