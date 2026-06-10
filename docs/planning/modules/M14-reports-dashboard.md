# M14 — Reports & Dashboard

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 3 |
| **Depends on** | M03+ (more modules = richer reports) |

## Scope

Dashboard KPIs, all BRD reports, Excel/PDF export.

## Reports

- Asset Register (core + custom fields per category)
- Movement Report
- Audit Report / Compliance
- Maintenance Report
- Disposal Report
- Asset Aging
- Depreciation Schedule (period, accumulated, book value)
- Kit Assignment History
- Utilization (assigned vs available)

## Routes

| Method | URI | Action |
|--------|-----|--------|
| GET | `/dashboard` | DashboardController |
| GET | `/reports/{type}` | ReportController@index |
| POST | `/reports/{type}/export` | Queued export |

## Tasks

- [ ] Dashboard widgets: totals, by status/category/branch, alerts summary
- [ ] Report filters consistent with `x-filter-bar`
- [ ] Excel export via Maatwebsite Excel
- [ ] PDF export via DomPDF
- [ ] Queue large exports; notify when ready (M12)
- [ ] `ReportService` implementation (stub from M07)
- [ ] Custom fields in asset register via `DynamicFieldService`
- [ ] Permission: `reports.view`, `reports.export`

## Acceptance criteria

- Dashboard loads under 2s on seed data
- Exports match on-screen filters
- Asset register includes category-specific columns grouped or flattened

## User flow

See parent plan UF-15.
