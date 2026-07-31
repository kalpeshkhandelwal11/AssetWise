---
name: project-module-status
description: "AssetWise implementation progress — M00, M01, M02 complete; M03 Asset Master is next"
metadata:
  type: project
---

M00, M01, and M02 are complete as of 2026-07-31.

**What's in M01 (done):**
- Session lifetime middleware (`CheckSessionLifetime`) with role-configurable timeout
- Force password change middleware + controller
- Login history (success/failed) via auto-discovered event listeners
- RBAC seed: Super Admin, Asset Manager, Department User, Auditor, Approver, Viewer
- Test suite: 93 tests, all passing
- Docs: `docs/developer-setup.md`, `docs/deployment-shared-hosting.md`

**What's in M02 (done):**
- Migrations + models: companies, asset_statuses, asset_types, priorities, movement_types, audit_types, disposal_types, maintenance_types, locations/buildings/floors/rooms
- `CompanyController` at `/admin/companies` — CRUD + toggle-active (M03 asset-check hook is a commented placeholder)
- `MasterController` at `/admin/masters/{entity}` — generic CRUD for 7 entity types; system statuses protected from delete/deactivate
- `LocationController` — 4-level hierarchy (location→building→floor→room) at `/admin/locations`; tree view with Alpine.js expand modals
- `SharedMastersSeeder` — seeds default company, all status/type/priority defaults, sample HQ location hierarchy
- Permissions added: `masters.view`, `companies.manage`
- Test suite: 129 tests, all passing (36 new M02 tests)

**Why:** M02 unblocks M03 (Asset Master needs company_id, location/room FKs, status FK).

**How to apply:** M03 Asset Master is next. Tables needed from M02: `companies.id`, `asset_statuses.id`, `rooms.id` (full location path). The `company_id` FK on assets is required and non-nullable.
