# M09 — Asset Movement

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Depends on** | M03, M08 |
| **Parallel with** | M10, M11, M16, M17 |

## Scope

Assignment, Return, Transfer, Custodian Change — all approval-gated. Exposes bulk apply API for **M17 kit/bundle** assignments.

## DB Tables

- `asset_movements` (asset_id, movement_type_id, from/to location/custodian/department, status, notes, verified_at, verified_by, kit_assignment_id nullable)
- `asset_status_histories` (asset_id, from_status_id, to_status_id, changed_by, reason)

Movement status: draft, pending_approval, approved, rejected, completed.

## Service

`MovementService::submit()`, `apply()`, `applyBulk(Collection $assets, MovementTarget $target, ?KitAssignment $kit)` — used by M17.

## Routes

| Method | URI | Action |
|--------|-----|--------|
| GET/POST | `/movements/create` | Movement wizard (single asset) |
| GET | `/movements` | Global history |
| GET | `/assets/{asset}/movements` | Per-asset history |
| POST | `/movements/{movement}/verify` | Post-completion verification |

Kit/bundle assignment routes live in **M17**.

## Tasks

- [ ] Movement wizard (single asset)
- [ ] Validate asset not disposed / not pending another movement
- [ ] Submit → `pending_approval` + `WorkflowService::submit`
- [ ] On approval → update asset, complete movement, status history
- [ ] `applyBulk()` for M17 kit assignments (atomic on single-approval)
- [ ] On reject → notify requester
- [ ] Movement tab on asset detail + global list with filters
- [ ] Permissions: `movement.create`, `movement.view`, `movement.verify`

## Acceptance criteria

- No movement applies without full approval chain
- History is append-only
- Custodian receives notification on completion
- Disposed assets cannot be moved
- Kit bulk apply creates linked movements sharing `kit_assignment_id`

## User flow

See parent plan UF-10 and UF-20.
