# M11 — Maintenance

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Depends on** | M03 only |
| **Parallel with** | M04, M05, M08 (can start early) |

## Scope

Preventive/corrective maintenance, AMC contracts, warranty records, service history, EOL, expiry alerts.

## DB Tables

- `maintenance_records` (asset_id, maintenance_type_id, dates, vendor, cost, description, status)
- `amc_contracts` (asset_id, vendor, start_date, end_date, coverage, cost)
- `warranty_records` (asset_id, provider, start_date, end_date, terms)

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/assets/{asset}/maintenance` | MaintenanceController |
| CRUD | `/assets/{asset}/amc` | AmcController |
| CRUD | `/assets/{asset}/warranty` | WarrantyController |
| GET | `/maintenance` | Global maintenance list |

## Tasks

- [ ] Maintenance record CRUD on asset + global list
- [ ] AMC contract CRUD; sync `assets.amc_expiry` for alerts
- [ ] Warranty records (multiple per asset); sync `assets.warranty_expiry`
- [ ] Service history timeline on asset detail
- [ ] Repair cost rollup per asset
- [ ] EOL flag + projected date field on asset
- [ ] Scheduled command: `alerts:expiry` — 30/7/1 day warranty/AMC alerts
- [ ] Permission: `maintenance.manage`

## Acceptance criteria

- Full service history visible on asset
- Scheduler sends alerts (email + in-app via M12)
- Multiple AMC/warranty records supported

## Parallel note

**Earliest Dev 2 module** — can start as soon as M03 merges (does not need M08).

## User flow

See parent plan UF-12.
