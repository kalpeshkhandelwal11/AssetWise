# M09 — Asset Movement

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Depends on** | M03, M08 |
| **Parallel with** | M10, M11 |

## Scope

Assignment, Return, Transfer, Custodian Change — all approval-gated.

## DB Tables

- `asset_movements` (asset_id, movement_type_id, from/to location/custodian/department, status, notes, verified_at, verified_by)
- `asset_status_histories` (asset_id, from_status_id, to_status_id, changed_by, reason)

Movement status: draft, pending_approval, approved, rejected, completed.

## Routes

| Method | URI | Action |
|--------|-----|--------|
| GET/POST | `/movements/create` | Movement wizard |
| GET | `/movements` | Global history |
| GET | `/assets/{asset}/movements` | Per-asset history |
| POST | `/movements/{movement}/verify` | Post-completion verification |

## Tasks

- [ ] Movement wizard (single + bulk assets)
- [ ] Validate asset not disposed / not pending another movement
- [ ] Submit → `pending_approval` + `WorkflowService::submit`
- [ ] On approval → update asset, complete movement, status history
- [ ] On reject → notify requester
- [ ] Movement tab on asset detail + global list with filters
- [ ] Permissions: `movement.create`, `movement.view`, `movement.verify`

## Acceptance criteria

- No movement applies without full approval chain
- History is append-only
- Custodian receives notification on completion
- Disposed assets cannot be moved

## User flow

See parent plan UF-10.
