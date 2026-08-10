# M09 — Asset Movement

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Depends on** | M03, M08 |
| **Parallel with** | M10, M11, M16, M17 |
| **Status** | ✅ done (2026-08-12) |

> **M08 integration.** Submitted with `WorkflowService::submit($movement, 'transfer', $actor)` (single) or `WorkflowService::submit($batch, 'transfer', $actor)` (bulk). `WorkflowSeeder` ships an active default chain (Approver → Asset Manager, 48h escalation each).
>
> M08 does **not** call `MovementService` directly. `ApplyAssetMovement` listens on `App\Events\ApprovalRequestApproved` (auto-discovered via its `handle()` type-hint — not also registered in `AppServiceProvider`), filtered on `$event->request->workflow->module === 'transfer'`, and branches on whether `$event->request->approvable` is an `AssetMovement` or `AssetMovementBatch`. A second listener, `MarkAssetMovementRejected`, listens on `ApprovalRequestRejected` for the same module and flips the movement/batch row to `rejected` — see "Implementation decisions" below for why this diverges from M05's tag-replacement pattern.
>
> **P9.1 resolved (2026-08-12):** bulk (non-kit) movement gets **one approval per batch**, not one per asset — see `docs/decisions-log.md`.

## Scope

Assignment, Return, Transfer, Custodian Change, and **Inter-Company Transfer** — all approval-gated. Exposes bulk apply API for **M17 kit/bundle** assignments. Inter-company transfers update `assets.company_id` on approval completion.

## DB Tables (as shipped)

- `asset_movements` (asset_id, movement_type_id, batch_id nullable → `asset_movement_batches`, kit_assignment_id nullable unconstrained, from/to company/location/custodian/department, to_status_id nullable, status, notes, verified_at, verified_by, approval_request_id, requested_by)
- `asset_movement_batches` — the bulk grouping/approvable (P9.1): movement_type_id, shared to_company/to_location/to_custodian/to_department/to_status, kit_assignment_id nullable, status, notes, approval_request_id, requested_by. **Reused by M17**: a kit assignment is the same model with `kit_assignment_id` set — build M17 on `MovementService::applyBulk()` rather than a second grouping table.
- `asset_status_histories` (asset_id, from_status_id, to_status_id, changed_by, reason, created_at only) — cross-module append-only ledger; M09 writes to it only when a movement's optional "New Status" field is set, but M11/M13 may write to it too later.

Movement status (both tables): `pending_approval`, `rejected`, `completed` — no `draft` (neither wizard has a save-for-later step) and no separate `approved` resting state (`apply()`/`applyBulk()` run synchronously inside the `ApprovalRequestApproved` listener, so a row goes straight from `pending_approval` to `completed`).

## Service (as shipped)

`MovementService::submit(Asset, array, User): AssetMovement`, `submitBatch(Collection, array, User): AssetMovementBatch`, `apply(AssetMovement)`, `applyBulk(AssetMovementBatch)`, `verify(AssetMovement, User)`, `permissionFor(MovementType): string`.

Deviates from the sketch above in two ways, both recorded in `docs/decisions-log.md`: plain arrays instead of a `MovementTarget` value object (consistency with the rest of the codebase — `TagService` etc. don't use value objects either), and `applyBulk(AssetMovementBatch $batch)` instead of `applyBulk(Collection $assets, MovementTarget $target, ?KitAssignment $kit)` (the batch model already carries the target + optional kit link, so the three separate arguments collapse into one).

`applyToAsset()` maps movement-type **code** → asset columns explicitly (not "if a to_* value is present, apply it") because `RETURN` must *clear* `custodian_id` rather than leave it untouched — reading `to_custodian_id` for a Return would silently no-op instead of returning the asset to the pool.

## Routes (as shipped)

| Method | URI | Action |
|--------|-----|--------|
| GET | `/movements` | Global history (filterable by type/status/search) |
| GET/POST | `/movements/create` | Movement wizard (single asset) |
| GET/POST | `/movements/bulk/create`, `/movements/bulk` | Bulk wizard — reached from the assets list's multi-select bar |
| POST | `/movements/{movement}/verify` | Post-completion verification |

No standalone `GET /assets/{asset}/movements` route shipped — per-asset history is a tab on `assets.show` (`$asset->movements`, eager-loaded), the same pattern the existing Tags and History tabs already use. Kit/bundle assignment routes remain M17's.

## Permissions (as shipped)

No new permissions — reused what `RolePermissionSeeder` already seeded ahead of M09: `movement.assign` gates Assignment/Return/Custodian Change, `movement.transfer` gates Transfer/Inter-Company Transfer, `movement.verify` gates the post-completion sign-off, `assets.view` gates read access, and `assets.bulk` (already used by M06 export) gates the multi-select bar on the assets list. `MovementService::permissionFor()` is the single source of truth for the assign/transfer split.

## Tasks

- [x] Movement wizard (single asset) — movement type dropdown includes **Inter-Company Transfer**
- [x] When movement type is Inter-Company Transfer: show `to_company_id` selector (companies master); same-company submission is rejected server-side
- [x] Validate asset not disposed / not pending another movement
- [x] Submit → `pending_approval` + `WorkflowService::submit('transfer')`
- [x] On approval → update asset location/custodian; **if inter-company transfer: also update `assets.company_id`**; complete movement; append status history (when a target status was set)
- [x] `applyBulk()` for bulk multi-select (P9.1) and reserved for M17 kit assignments (atomic on single-approval); propagates inter-company company update
- [x] On reject → notify requester (free from `WorkflowService::reject()`, no module-specific code needed) + `MarkAssetMovementRejected` clears the pending-movement guard
- [x] Movement tab on asset detail + global list with filters (movement type + status; no company filter — company isn't a movement-row column for every type)
- [x] Permissions: reused `movement.assign` / `movement.transfer` / `movement.verify` / `assets.bulk` (see above) instead of inventing `movement.create` / `movement.view` / `movement.intercompany`

## Acceptance criteria

- [x] No movement applies without full approval chain
- [x] History is append-only
- [x] Custodian receives notification on completion
- [x] Disposed assets cannot be moved
- [x] Kit bulk apply creates linked movements sharing `kit_assignment_id` (denormalized onto each `asset_movements` row from its batch at apply time)
- [x] After inter-company transfer completes, `assets.company_id` reflects the destination company
- [x] Asset ownership history visible on asset detail movement tab

## User flow

See parent plan UF-10 and UF-20.
