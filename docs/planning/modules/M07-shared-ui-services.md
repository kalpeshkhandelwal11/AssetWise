# M07 — Shared UI Components & Services

| | |
|--|--|
| **Developer** | Dev 1 (ongoing) |
| **Phase** | 1 (continuous) |
| **Depends on** | M00 |

## Scope

Reusable Blade/Alpine components and service stubs used by all modules.

## Blade Components

- `x-app-layout` — sidebar + topbar
- `x-data-table` — sortable, searchable, paginated
- `x-filter-bar` — date range, dropdowns, query-string persistence
- `x-master-form` — standard create/edit for masters
- `x-dynamic-fields` — renders resolved field set (M04 fills logic)
- `x-attachment-uploader`
- `x-status-badge`
- `x-confirm-modal`
- `x-breadcrumb`

## Service Stubs

| Service | Phase 1 | Later |
|---------|---------|-------|
| `NotificationService` | Interface + DB table stub | M12 completes |
| `ReportService` | Interface only | M14 completes |
| `WorkflowService` | Interface only | M08 completes |

## Tasks

- [ ] Component library in `resources/views/components/`
- [ ] Sidebar nav driven by permissions (config or composer)
- [ ] Toast/flash notification component
- [ ] Form error display pattern
- [ ] Print layout for labels/reports

## Acceptance criteria

- M02 masters use `x-data-table` and `x-master-form`
- No module duplicates table/filter markup
- Service interfaces documented for Dev 2

## Parallel note

Dev 1 maintains this while building M01–M06. Dev 2 uses components for M08+.
