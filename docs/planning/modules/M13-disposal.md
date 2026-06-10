# M13 — Disposal & Scrap

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 3 |
| **Depends on** | M03, M08 |

## Scope

Disposal requests, approval, write-off, scrap completion, disposal history.

## DB Tables

- `disposal_requests` (asset_id, disposal_type_id, reason, status, requested_by, written_off_at, scrapped_at)

Status: draft, pending_approval, approved, rejected, written_off, scrapped.

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/disposals` | DisposalController |
| POST | `/disposals/{id}/submit` | Submit for approval |
| POST | `/disposals/{id}/write-off` | Write off (post-approval) |
| POST | `/disposals/{id}/scrap` | Complete scrap |

## Tasks

- [ ] Disposal request form with attachments
- [ ] Submit → M08 disposal workflow
- [ ] On approval → enable write-off action
- [ ] Write-off → financial record fields
- [ ] Scrap complete → asset status Disposed, lock movements
- [ ] Disposal history report
- [ ] Permissions: `disposal.request`, `disposal.approve`, `disposal.complete`

## Acceptance criteria

- Disposed assets cannot move or be reassigned
- Full approval chain required
- History auditable via activity log

## User flow

See parent plan UF-13.
