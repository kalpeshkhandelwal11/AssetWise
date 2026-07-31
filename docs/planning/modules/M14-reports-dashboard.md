# M14 — Reports & Dashboard

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 3 |
| **Depends on** | M03+ (more modules = richer reports) |

## Scope

Dashboard KPIs, all BRD reports, Excel/PDF export.

## Reports

- Asset Register (core + custom fields per category; includes company column; filterable by company)
- Movement Report (includes inter-company transfers; filterable by from/to company)
- **Inter-Company Transfer Report** — dedicated view showing all transfers between companies, from/to company, date, approver, asset details
- Audit Report / Compliance
- Maintenance Report
- Disposal Report
- Asset Aging
- Depreciation Schedule (period, accumulated, book value)
- Kit Assignment History
- Utilization (assigned vs available; breakable down by company)

## Routes

| Method | URI | Action |
|--------|-----|--------|
| GET | `/dashboard` | DashboardController |
| GET | `/reports/{type}` | ReportController@index |
| POST | `/reports/{type}/export` | Queued export |

## Tasks

- [ ] Dashboard widgets: totals **by company**, by status/category/branch, alerts summary; add company selector to dashboard filter
- [ ] Report filters consistent with `x-filter-bar`; all asset-level reports accept **company filter**
- [ ] Excel export via Maatwebsite Excel
- [ ] PDF export via DomPDF
- [ ] Queue large exports; notify when ready (M12)
- [ ] `ReportService` implementation (stub from M07)
- [ ] Custom fields in asset register via `DynamicFieldService`
- [ ] **Inter-Company Transfer Report**: filter by date range, from company, to company; show asset, movement date, requester, approver, completion date; export Excel/PDF
- [ ] Asset register includes company name/code column
- [ ] Permission: `reports.view`, `reports.export`

## Acceptance criteria

- Dashboard loads under 2s on seed data
- Dashboard company filter scopes all widgets
- Exports match on-screen filters
- Asset register includes category-specific columns grouped or flattened plus company column
- Inter-Company Transfer report shows full transfer history with company context

## User flow

See parent plan UF-15.
