---
name: project-module-status
description: "AssetWise implementation progress — M00–M02 complete, M03 Asset Master ready to implement"
metadata:
  type: project
---

M00, M01, and M02 are complete as of 2026-07-31. M03 is fully planned and ready to implement.

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
- `LocationController` — 4-level hierarchy at `/admin/locations`; tree view with Alpine.js expand modals
- `SharedMastersSeeder` — seeds default company, all status/type/priority defaults, sample HQ location hierarchy
- Permissions added: `masters.view`, `companies.manage`
- Test suite: 129 tests, all passing (36 new M02 tests)

**M03 Asset Master — ready to implement (decisions confirmed 2026-07-31):**

All pre-implementation decisions are resolved. See `docs/planning/modules/M03-asset-master.md` for the full spec.

Key decisions:
- **Departments + Branches**: create as new simple master tables (name, code, is_active) — same M02 pattern
- **Category tree UI**: parent-dropdown list (not drag-and-drop) — same M02 pattern
- **Location cascade**: API endpoints in `routes/api.php` — `GET /api/buildings?location_id=X` etc., Alpine.js fetches, reused by M09
- **Photo storage**: `storage/app/public/assets/{id}/photos/` — 10 MB max
- **Attachment preview**: download link only (inline preview deferred to M07)
- **Attachment max size**: 20 MB

What M03 will add:
- Migrations: departments, branches, asset_categories, assets, asset_photos, asset_attachments
- Models: Department, Branch, AssetCategory (self-ref), Asset (with all relationships + soft delete), AssetPhoto, AssetAttachment
- `Asset::hasCustomFieldData()` stub → returns false (M04 replaces)
- `AssetPolicy` (view/create/edit/delete)
- `CategoryController`, `AssetController`, `PhotoController`, `AttachmentController`
- `Api\LocationCascadeController` (buildings/floors/rooms JSON)
- `AssetService` — activity log on all mutations
- Asset list, create/edit form (category first, M04 placeholder div, cascading location), detail page (tabs: Summary, Photos, Attachments, History)
- ~40 new tests

**Why M03 matters:** It's the central module everything else builds on. M04 (dynamic fields), M05 (QR tags), M06 (bulk import), M09 (movement), M10 (audit), M11 (maintenance), M13 (disposal), M14 (reports) all depend on the stable `Asset` model M03 exposes.

**How to apply:** Pick up from `docs/planning/modules/M03-asset-master.md`. Everything is pre-decided — no questions remaining before coding starts.
