# M14 — Reports & Dashboard

**Status: ✅ done** (Kit Assignment History excluded — pending M17, see
[`M17-asset-kits.md`](M17-asset-kits.md)). Maintenance and AMC & Warranty shipped in a
follow-up once M11 landed — see
[`M14-pending-reports-plan.md`](M14-pending-reports-plan.md).

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 3 |
| **Depends on** | M03+ (more modules = richer reports) |

## Scope

Dashboard KPIs, all BRD reports, Excel/PDF export.

## Reports

- [x] Asset Register (core + custom fields per category; includes company column; filterable by company; also carries `is_eol`/`eol_projected_date`)
- [x] Movement Report (includes inter-company transfers; filterable by from/to company)
- [x] **Inter-Company Transfer Report** — dedicated view showing all transfers between companies, from/to company, date, approver, asset details
- [x] Audit Report / Compliance
- [x] Audit Campaign (verification findings — not in the original list, added alongside Audit/Compliance)
- [x] Maintenance Report — service history (`maintenance_records`); AMC/warranty split into their own report below rather than folded in, since the BRD's "Maintenance Reports" line covers three tables with different shapes
- [x] AMC & Warranty — not an original BRD line item by this name, but "AMC Tracking" / "Warranty Tracking" needed a home; one row per contract via a UNION of `amc_contracts`/`warranty_records`
- [x] Disposal Report
- [x] Asset Aging
- [x] Depreciation Schedule (period, accumulated, book value)
- [ ] Kit Assignment History — pending M17 (`kit_assignments`/`kit_assignment_items` don't exist yet); registered in `ReportRegistry` as disabled
- [x] Utilization (assigned vs available; breakable down by company)

## Routes

| Method | URI | Action |
|--------|-----|--------|
| GET | `/dashboard` | DashboardController |
| GET | `/reports/{type}` | ReportController@index |
| POST | `/reports/{type}/export` | Queued export |

## Tasks

- [x] Dashboard widgets: totals **by company**, by status/category/branch, alerts summary; add company selector to dashboard filter
- [x] Report filters consistent with `x-filter-bar`; all asset-level reports accept **company filter**
- [x] Excel export via Maatwebsite Excel
- [x] PDF export via DomPDF
- [x] Queue large exports; notify when ready (M12)
- [x] `ReportService` implementation (stub from M07)
- [x] Custom fields in asset register via `DynamicFieldService`
- [x] **Inter-Company Transfer Report**: filter by date range, from company, to company; show asset, movement date, requester, approver, completion date; export Excel/PDF
- [x] Asset register includes company name/code column
- [x] Permission: `reports.view`, `reports.export`

## Acceptance criteria

- Dashboard loads under 2s on seed data
- Dashboard company filter scopes all widgets
- Exports match on-screen filters
- Asset register includes category-specific columns grouped or flattened plus company column
- Inter-Company Transfer report shows full transfer history with company context

## User flow

See parent plan UF-15.
