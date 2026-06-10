# M03 — Asset Master Core

| | |
|--|--|
| **Developer** | Dev 1 |
| **Phase** | 1 |
| **Depends on** | M01, M02 |
| **Blocks** | M04, M05, M06, M09, M10, M11, M13, M14 |

## Scope

Asset categories (tree), asset CRUD, search/filter, photos, attachments, asset detail page. **No custom fields yet** (M04) — stub hook for dynamic section.

## DB Tables

- `asset_categories` (parent_id, name, code, is_active, sort_order)
- `assets` (full core columns; `asset_tag` denormalized from active tag assignment — optional at create)
- `asset_photos` (asset_id, path, is_primary, sort_order)
- `asset_attachments` (asset_id, type enum, path, original_name, mime)

Attachment types: invoice, warranty_card, manual, agreement.

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/admin/categories` | CategoryController |
| CRUD | `/assets` | AssetController |
| GET | `/assets/{asset}` | Asset detail (tabs: summary, photos, attachments, history placeholder) |
| POST | `/assets/{asset}/photos` | PhotoController |
| POST | `/assets/{asset}/attachments` | AttachmentController |

## Tasks

- [ ] Category tree CRUD (nested list or parent selector)
- [ ] Asset create/edit form — core fields + category + asset_type (independent)
- [ ] Category selected first; placeholder div for M04 dynamic fields
- [ ] `Asset::hasCustomFieldData()` returns false until M04
- [ ] Asset list: search, filters, pagination
- [ ] Asset detail with activity timeline (Spatie)
- [ ] Tag section placeholder: "No tag assigned" until M05 assigns from pool
- [ ] Photo upload (multiple, set primary)
- [ ] Attachment upload with type + document preview (PDF/image)
- [ ] Policies: `assets.view/create/edit/delete`
- [ ] Soft delete assets

## Acceptance criteria

- Assets created with category, type, location, custodian
- Search and filters work on core fields
- Photos and attachments upload and display
- Category tree supports parent/child

## Handoff to Dev 2

Expose stable `Asset` model with relationships: `category`, `assetType`, `status`, `custodian`, `location`, `photos`, `attachments`. Dev 2 can start **M11 Maintenance** once this merges.
