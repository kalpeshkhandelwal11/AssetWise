# M09 — Asset Movement

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Depends on** | M03, M08 |
| **Parallel with** | M10, M11, M16, M17 |

## Scope

Assignment, Return, Transfer, Custodian Change, and **Inter-Company Transfer** — all approval-gated. Exposes bulk apply API for **M17 kit/bundle** assignments. Inter-company transfers update `assets.company_id` on approval completion.

## DB Tables

- `asset_movements` (asset_id, movement_type_id, from_company_id nullable, to_company_id nullable, from/to location/custodian/department, status, notes, verified_at, verified_by, kit_assignment_id nullable)
- `asset_status_histories` (asset_id, from_status_id, to_status_id, changed_by, reason)

Movement status: draft, pending_approval, approved, rejected, completed.

### Inter-Company Transfer columns on `asset_movements`

| Column | Notes |
|--------|-------|
| `from_company_id` | FK `companies` nullable — populated on inter-company transfer |
| `to_company_id` | FK `companies` nullable — target company for inter-company transfer |

> For non-inter-company movements these remain null.

## Service

`MovementService::submit()`, `apply()`, `applyBulk(Collection $assets, MovementTarget $target, ?KitAssignment $kit)` — used by M17.

On `apply()` for an inter-company transfer: update `assets.company_id = to_company_id` atomically with the movement completion.

## Routes

| Method | URI | Action |
|--------|-----|--------|
| GET/POST | `/movements/create` | Movement wizard (single asset) |
| GET | `/movements` | Global history |
| GET | `/assets/{asset}/movements` | Per-asset history |
| POST | `/movements/{movement}/verify` | Post-completion verification |

Kit/bundle assignment routes live in **M17**.

## Tasks

- [ ] Movement wizard (single asset) — movement type dropdown includes **Inter-Company Transfer**
- [ ] When movement type is Inter-Company Transfer: show `to_company_id` selector (companies master, excluding current company)
- [ ] Validate asset not disposed / not pending another movement
- [ ] Submit → `pending_approval` + `WorkflowService::submit('transfer')`
- [ ] On approval → update asset location/custodian; **if inter-company transfer: also update `assets.company_id`**; complete movement; append status history
- [ ] `applyBulk()` for M17 kit assignments (atomic on single-approval); propagate inter-company company update if applicable
- [ ] On reject → notify requester
- [ ] Movement tab on asset detail + global list with filters (include **company filter**, **movement type filter**)
- [ ] Permissions: `movement.create`, `movement.view`, `movement.verify`, `movement.intercompany`

## Acceptance criteria

- No movement applies without full approval chain
- History is append-only
- Custodian receives notification on completion
- Disposed assets cannot be moved
- Kit bulk apply creates linked movements sharing `kit_assignment_id`
- After inter-company transfer completes, `assets.company_id` reflects the destination company
- Asset ownership history (company changes) visible on asset detail movement tab

## User flow

See parent plan UF-10 and UF-20.
