# M17 — Asset Kits & Bundles

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Depends on** | M03, M08, M09 |
| **Parallel with** | M10, M11, M16 |

## Scope

**Kit templates** (named bundles like "Developer Workstation") and **ad-hoc bundles** (multi-select assets at assignment). Assign entire group to custodian/location in one action. **Approval mode configurable:** one request for whole kit OR per-asset.

## DB Tables

- `kits`, `kit_items`, `kit_assets`
- `kit_assignments`, `kit_assignment_items`

## System setting

`config/assetwise.php` → `kit_assignment_approval_mode`: `single` | `per_asset`  
Admin UI under Settings (M17).

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/kits` | Kit template master |
| CRUD | `/kits/{kit}/items` | Template line items |
| POST | `/kits/{kit}/assets` | Link asset to slot |
| GET/POST | `/kit-assignments/create` | Assign kit or ad-hoc bundle |
| GET | `/kit-assignments` | Assignment history |

## Tasks

- [ ] Kit template CRUD (name, code, description)
- [ ] Kit item lines: label, optional category constraint, quantity
- [ ] Link physical assets to kit slots; validate category match
- [ ] Kit readiness indicator (all slots filled?)
- [ ] Assignment wizard: pick kit OR ad-hoc multi-select assets
- [ ] Movement type + to custodian/location/department
- [ ] On submit: read `kit_assignment_approval_mode`
  - `single` → one `approval_request` on `kit_assignment`
  - `per_asset` → one request per `kit_assignment_item`
- [ ] On approve: create `asset_movements` for each item via M09 service
- [ ] Kit tab on asset detail (which kits include this asset)
- [ ] Permissions: `kits.manage`, `kits.assign`, `kits.view`

## Acceptance criteria

- Kit template assign moves all linked assets together
- Ad-hoc bundle works without saved template
- Single-approval mode: one reject cancels entire bundle
- Per-asset mode: independent approval per asset
- Disposed or pending-movement assets blocked from kit assignment
- Assignment history auditable

## Handoff

- M09 `MovementService::applyBulk($assets, $target)` called on kit approval
- M08 supports batch-linked approval for `kit_assignment` morph type

## User flow

See parent plan UF-20.
