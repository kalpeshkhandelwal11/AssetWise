# M13 — Disposal & Scrap

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 3 |
| **Depends on** | M03, M08 |
| **Status** | ✅ done (2026-08-13) |

> **M08 integration.** `disposal` is in the `approval_workflows.module` enum with **no seeded workflow** — deliberate, and still true after this module shipped (tests build a single-step workflow inline, the same way an admin would via `/admin/workflows`). `WorkflowService::submit()` throws a `ValidationException` (key `workflow`) if zero — or more than one — active workflow exists for the module; `DisposalService::submit()` wraps its row-creation + `WorkflowService::submit()` call in a DB transaction so that failure never leaves an orphaned `disposal_requests` row behind (the same fix was retrofitted onto `MovementService::submit()`, which had the identical latent gap — dormant there only because `transfer` happens to always be seeded).
>
> Submitted with `WorkflowService::submit($disposalRequest, 'disposal', $actor)`. Unlike M09, approval does **not** perform the write-off — `MarkDisposalApproved` (listening on `ApprovalRequestApproved`, filtered on `workflow->module === 'disposal'`) only flips the request to `approved`, which unlocks a separate manual write-off action, followed by a separate scrap-completion action that is the one that actually touches the asset. `MarkDisposalRejected` (on `ApprovalRequestRejected`) flips the request to `rejected`, clearing `Asset::hasPendingDisposal()`.

## Scope

Disposal requests, approval, write-off, scrap completion, disposal history.

## DB Tables (as shipped)

- `disposal_requests` (asset_id, disposal_type_id, reason, status, approval_request_id, requested_by, disposal_value, written_off_at, written_off_by, scrapped_at, scrapped_by)

Status: `pending_approval`, `approved`, `rejected`, `written_off`, `scrapped` — no `draft` (the form submits straight to pending, same call as M09).

## Routes (as shipped)

| Method | URI | Action |
|--------|-----|--------|
| GET | `/disposals` | Index — own requests, or everyone's for `disposal.approve`/`disposal.complete` holders |
| GET/POST | `/disposals/create`, `/disposals` | Request form / submit |
| GET | `/disposals/{disposal}` | Detail — status, approval-progress link, write-off/scrap actions |
| POST | `/disposals/{disposal}/write-off` | Write off (post-approval; captures optional `disposal_value`) |
| POST | `/disposals/{disposal}/scrap` | Complete scrap — sets `assets.status_id` to Disposed |

No edit/destroy routes: like `AssetMovement`, a submitted disposal request is immutable — a rejected one is resubmitted by calling `submit()` again, not edited in place.

## Tasks

- [x] Disposal request form — attachments reuse the asset's existing Attachments tab (M03) rather than a second, disposal-specific attachment table
- [x] Submit → M08 disposal workflow
- [x] On approval → enable write-off action (status `approved`; asset untouched)
- [x] Write-off → financial record field (`disposal_value`, optional)
- [x] Scrap complete → asset status Disposed (via `AssetStatus.code = 'DISPOSED'` lookup, not a hardcoded id), append `asset_status_histories` (M09's ledger table, reused here as designed), lock movements — `MovementService::assertMovable()` now also rejects a disposed asset or one with `hasPendingDisposal()`, and `DisposalService::assertDisposable()` symmetrically rejects an asset with `hasPendingMovement()`
- [x] Disposal history — the `/disposals` index, filterable by type/status/search (a dedicated report is M14's job)
- [x] Permissions: `disposal.request`, `disposal.approve` (already seeded ahead of M13 — reused, not redefined), plus new `disposal.complete` for write-off/scrap

## Acceptance criteria

- [x] Disposed assets cannot move or be reassigned (`MovementService::assertMovable()` + `DisposalService::assertDisposable()` both check `Asset::isDisposed()`)
- [x] Full approval chain required (write-off blocked unless status is `approved`; scrap blocked unless status is `written_off`)
- [x] History auditable via activity log — `DisposalRequest` uses `LogsActivity` (`logFillable()->logOnlyDirty()->useLogName('disposal')`), visible in the existing global Activity Log viewer

## User flow

See parent plan UF-13.
