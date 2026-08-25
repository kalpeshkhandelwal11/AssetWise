# M07 — Shared UI Components & Services

| | |
|--|--|
| **Developer** | Dev 1 (ongoing) |
| **Phase** | 1 (continuous) |
| **Depends on** | M00 |
| **Status** | ✅ done — retrofit pass across all M01–M06 list/filter screens (2026-08-10) |

> **Shipped:** `x-data-table` (card + pagination-footer wrapper, tolerates both paginators and plain collections), `x-filter-bar` (GET filter form chrome), `x-status-badge` (color/label/dot pill covering every active/inactive, success/failed, and multi-state badge in the app), `x-breadcrumb`, `x-confirm-modal` (self-contained Alpine confirm dialog, replacing native `confirm()` on delete actions). Retrofitted into `admin/masters`, `admin/companies`, `admin/categories`, `admin/category-fields`, `admin/login-history`, `admin/roles`, `admin/users`, `admin/tags`, `admin/activity-log`, `admin/workflows`, `approvals`, `modules/assets/index`, `modules/assets/import/index`, `modules/assets/import/show` — 372 tests still green after the pass.
>
> **Not built** (deferred, no current caller): a literal `x-master-form` component — the add/edit Alpine modal in `admin/masters/index.blade.php` still lives inline since its field set varies per entity (color picker, system-flag, etc.); `x-attachment-uploader` — no module needs a generic uploader yet; a standalone print layout component — M05's PDF/Word label sheets already have their own dedicated Blade/DomPDF views. `WorkflowService`/`NotificationService` interfaces were already documented for Dev 2 when M08 shipped.
>
> **2026-08-26 follow-up:** `layouts/sidebar-nav.blade.php` was retrofitted the same way — `x-sidebar-link`, `x-sidebar-group`, `x-sidebar-section` (`resources/views/components/`) replace the repeated hover/active/transition class strings with grouped, labeled sections (Asset Management, Workflow & Reports, Administration), `x-collapse` expand/collapse animation, and `aria-expanded`/`aria-controls`/`aria-current`. All existing `@can`/`@canany` gates and the disabled Phase 2/3 block are unchanged — components only, no permission logic moved. `layouts/app.blade.php`'s desktop sidebar-collapse button was restyled alongside it: one panel/divider icon (`aria-controls="app-sidebar"`) whose Tailwind text-shade toggles via Alpine `:class` bound to `sidebarCollapsed`, instead of swapping between two different icon shapes.

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
