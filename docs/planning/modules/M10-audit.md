# M10 — Audit & Verification

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Status** | ✅ done |
| **Depends on** | M03, M05 |
| **Parallel with** | M09, M11 |

## Scope

Audit campaigns, physical/QR/manual verification, missing/damaged tracking, compliance export.

## DB Tables

- `audit_campaigns` (name, audit_type_id, description, start_date, end_date, scope json, status enum draft/active/closed, activated_at, closed_at, created_by, closed_by)
- `audit_campaign_auditors` (campaign_id, user_id) — per-campaign auditor assignment (not in the original spec; see decisions-log)
- `audit_items` (campaign_id, asset_id, status enum, verified_by, verified_at, notes, photo_path, expected_location_id, expected_custodian_id)

Item status: pending, verified, missing, damaged. `expected_location_id`/`expected_custodian_id`
are snapshotted from the asset at activation time, not read live.

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

- [x] Campaign CRUD with scope filters (company, category, asset type, status, location, department, branch)
- [x] Activate → snapshot assets into `audit_items`
- [x] Auditor UI: QR scan (M05 `/scan/{tag_number}` URL, routes into verify when a pending item exists), manual search, exception notes + photo
- [x] Progress dashboard (% verified, exceptions)
- [x] Close campaign → lock items (verification rejected once `status = closed`)
- [x] Excel/PDF compliance report (per-campaign at `/audits/campaigns/{id}/report`, plus a cross-campaign `audit_campaign` entry in M14's `ReportRegistry`)
- [x] Notify auditors on campaign activate/close (via M08's `NotificationService` stub, same contract M11 uses — M12 hasn't shipped a full implementation yet)
- [x] Permissions: `audit.manage`, `audit.verify` (both already seeded ahead of this module)

## Acceptance criteria

- [x] QR scan marks correct audit item verified
- [x] Missing/damaged captured with evidence (notes required, photo optional)
- [x] Campaign cannot verify after closed
- [x] Report lists all exceptions

## User flow

See parent plan UF-11. One addition beyond the diagram: activation is gated on the campaign
having at least one assigned auditor and a non-empty scope (see decisions-log).
