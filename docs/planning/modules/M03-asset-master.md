# M03 — Asset Master Core

| | |
|--|--|
| **Developer** | Dev 1 |
| **Phase** | 1 |
| **Depends on** | M01, M02 |
| **Blocks** | M04, M05, M06, M09, M10, M11, M13, M14 |
| **Status** | 🟡 Ready to implement — all decisions made |

## Scope

Asset categories (tree), asset CRUD, search/filter, photos, attachments, asset detail page. **No custom fields yet** (M04) — stub hook for dynamic section. Each asset belongs to a **company** (from M02); company can change via inter-company transfer (M09).

---

## Pre-implementation Decisions (confirmed 2026-07-31)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Departments & Branches** | Create as new master tables now | `assets` needs `department_id` + `branch_id` FKs; simple name/code/is_active tables, same pattern as M02 masters |
| **Category tree UI** | Parent-dropdown list | Same pattern as M02 masters — list view with Parent Category dropdown on form; no drag-and-drop tree needed for MVP |
| **Location cascade** | API endpoints in `routes/api.php` | `GET /api/buildings?location_id=X`, `/api/floors?building_id=X`, `/api/rooms?floor_id=X` — Alpine.js fetches; reused by M09 movement forms |
| **Photo storage** | `storage/app/public/assets/{id}/photos/` | Standard Laravel filesystem; symlink already in place from M00 |
| **Attachment preview** | Download link only | Inline PDF/image preview is a M07 UI enhancement |
| **Max file sizes** | Photos 10 MB, attachments 20 MB | Enforced via validation rules in controllers |
| **`company_id` mutability** | Read-only after create for standard users | Only changes via approved inter-company transfer (M09); `companies.manage` holders can edit in admin |

---

## DB Tables

### New in M03

- `departments` (name, code unique, is_active)
- `branches` (name, code unique, is_active)
- `asset_categories` (parent_id nullable FK self, name, code unique, description, is_active, sort_order, soft_deletes)
- `assets` (full core columns — see below; soft_deletes)
- `asset_photos` (asset_id, path, is_primary, sort_order)
- `asset_attachments` (asset_id, type enum, path, original_name, mime, size)

Attachment types: `invoice`, `warranty_card`, `manual`, `agreement`.

### Full `assets` columns

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `asset_tag` | varchar nullable | Denormalized from active tag assignment; optional at create; synced by M05 |
| `name` | varchar | Required |
| `description` | text nullable | |
| `serial_number` | varchar nullable | |
| `model` | varchar nullable | |
| `manufacturer` | varchar nullable | |
| `company_id` | FK `companies` | **Required**; read-only after creation for standard users |
| `category_id` | FK `asset_categories` | Required; locked once custom field data exists (M04 enforces) |
| `asset_type_id` | FK `asset_types` | Required; independent of category |
| `status_id` | FK `asset_statuses` | Required; defaults to Available |
| `location_id` | FK `locations` nullable | |
| `building_id` | FK `buildings` nullable | Must belong to location |
| `floor_id` | FK `floors` nullable | Must belong to building |
| `room_id` | FK `rooms` nullable | Must belong to floor |
| `custodian_id` | FK `users` nullable | Assigned user |
| `department_id` | FK `departments` nullable | |
| `branch_id` | FK `branches` nullable | |
| `purchase_date` | date nullable | |
| `purchase_cost` | decimal(15,2) nullable | |
| `vendor` | varchar nullable | |
| `warranty_expiry` | date nullable | Quick-alert field; full warranty history in M11 |
| `amc_expiry` | date nullable | Quick-alert field; full AMC contracts in M11 |
| `notes` | text nullable | |
| `is_active` | boolean default true | |
| `created_by` | FK `users` | |
| `updated_by` | FK `users` nullable | |
| `deleted_at` | timestamp nullable | Soft delete |
| `timestamps` | | |

---

## Routes

| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/admin/categories` | `CategoryController@index` | `assets.view` |
| GET | `/admin/categories/create` | `CategoryController@create` | `assets.create` |
| POST | `/admin/categories` | `CategoryController@store` | `assets.create` |
| GET | `/admin/categories/{cat}/edit` | `CategoryController@edit` | `assets.edit` |
| PUT | `/admin/categories/{cat}` | `CategoryController@update` | `assets.edit` |
| DELETE | `/admin/categories/{cat}` | `CategoryController@destroy` | `assets.delete` |
| GET | `/assets` | `AssetController@index` | `assets.view` |
| GET | `/assets/create` | `AssetController@create` | `assets.create` |
| POST | `/assets` | `AssetController@store` | `assets.create` |
| GET | `/assets/{asset}` | `AssetController@show` | `assets.view` |
| GET | `/assets/{asset}/edit` | `AssetController@edit` | `assets.edit` |
| PUT | `/assets/{asset}` | `AssetController@update` | `assets.edit` |
| DELETE | `/assets/{asset}` | `AssetController@destroy` | `assets.delete` |
| POST | `/assets/{asset}/photos` | `PhotoController@store` | `assets.edit` |
| DELETE | `/assets/{asset}/photos/{photo}` | `PhotoController@destroy` | `assets.edit` |
| PATCH | `/assets/{asset}/photos/{photo}/primary` | `PhotoController@setPrimary` | `assets.edit` |
| POST | `/assets/{asset}/attachments` | `AttachmentController@store` | `assets.edit` |
| DELETE | `/assets/{asset}/attachments/{att}` | `AttachmentController@destroy` | `assets.edit` |
| GET | `/api/buildings` | `Api\LocationCascadeController@buildings` | auth |
| GET | `/api/floors` | `Api\LocationCascadeController@floors` | auth |
| GET | `/api/rooms` | `Api\LocationCascadeController@rooms` | auth |

---

## Implementation Checklist

- [x] Migrations: departments, branches, asset_categories, assets, asset_photos, asset_attachments
- [x] Models: Department, Branch, AssetCategory (self-referential), Asset (with all relationships), AssetPhoto, AssetAttachment
- [x] `Asset::hasCustomFieldData()` → returns `false` (stub; M04 replaces with real check)
- [x] `AssetPolicy` — view/create/edit/delete
- [x] `CategoryController` — CRUD with parent-dropdown tree (admin)
- [x] `AssetController` — CRUD + search/filter (tag, name, serial, custodian, company, status, type, category, location, date range) + soft delete
- [x] `PhotoController` — store (multi-upload), destroy, set-primary
- [x] `AttachmentController` — store (with type), destroy
- [x] `Api\LocationCascadeController` — buildings, floors, rooms (JSON for Alpine)
- [x] `AssetService` — create/update with `created_by`/`updated_by`, Spatie activity log (before/after on update)
- [x] Asset create/edit form — category dropdown first; M04 placeholder div; cascading location selects via API
- [x] Asset detail page — tabs: Summary, Photos, Attachments, History (Spatie timeline); tag section placeholder
- [x] Asset list — searchable, filterable, paginated; company column prominent
- [x] Sidebar nav — wire Assets and Categories links
- [x] SharedMastersSeeder — add sample departments and branches
- [x] RolePermissionSeeder — no new permissions needed (assets.* already seeded)
- [x] Tests — CategoryTest, AssetTest, PhotoTest, AttachmentTest, LocationCascadeApiTest (~40 tests)

---

## Acceptance Criteria

- Assets created with company (required), category, type, location, custodian
- Company shown prominently on asset list and detail
- Search and filters work across all core fields including company
- Photos upload, display, and primary can be set
- Attachments upload with type; download link shown
- Category tree supports parent/child; categories page shows hierarchy via indentation
- Cascading location selects work (building list updates when location changes)
- Soft-deleted assets not shown in list; accessible via `?show_deleted=1` with `assets.delete` permission
- Activity log entry created on asset create/update/delete

## Handoff to Dev 2

Expose stable `Asset` model with relationships: `company`, `category`, `assetType`, `status`, `custodian`, `department`, `branch`, `location`, `building`, `floor`, `room`, `photos`, `attachments`. Dev 2 can start **M11 Maintenance** once this merges.
