# M10 — Audit & Verification

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Depends on** | M03, M05 |
| **Parallel with** | M09, M11 |

## Scope

Audit campaigns, physical/QR/manual verification, missing/damaged tracking, compliance export.

## DB Tables

- `audit_campaigns` (name, audit_type_id, start_date, end_date, scope json, status, created_by)
- `audit_items` (campaign_id, asset_id, status enum, verified_by, verified_at, notes, photo_path)

Item status: pending, verified, missing, damaged.

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/audits/campaigns` | CampaignController |
| POST | `/audits/campaigns/{id}/activate` | Generate items |
| POST | `/audits/campaigns/{id}/close` | Close campaign |
| GET | `/audits/verify` | Auditor verification UI |
| POST | `/audits/items/{id}/verify` | Mark verified/missing/damaged |
| GET | `/audits/campaigns/{id}/report` | Compliance export |

## Tasks

- [ ] Campaign CRUD with scope filters (location, category, branch)
- [ ] Activate → snapshot assets into `audit_items`
- [ ] Auditor UI: QR scan (M05 URL), manual search, exception notes + photo
- [ ] Progress dashboard (% verified, exceptions)
- [ ] Close campaign → lock items
- [ ] Excel/PDF compliance report
- [ ] Notify auditors on campaign activate (M12)
- [ ] Permissions: `audit.manage`, `audit.verify`

## Acceptance criteria

- QR scan marks correct audit item verified
- Missing/damaged captured with evidence
- Campaign cannot verify after closed
- Report lists all exceptions

## User flow

See parent plan UF-11.
