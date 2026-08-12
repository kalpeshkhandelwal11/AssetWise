# M14 — Pending Reports (Implementation Plan)

## Context

M14 shipped 9 of its 11 registered report types. Two entries in
`App\Services\Reports\ReportRegistry` are still `'enabled' => false`, rendered on
`/reports` as "Coming soon" and 404'd by `ReportRegistry::assertEnabled()`:

| Entry | Blocked on | Status now |
|-------|-----------|------------|
| `maintenance` | M11 Maintenance | **Unblocked** — M11 shipped (`maintenance_records`, `amc_contracts`, `warranty_records` all exist and are populated by `MaintenanceService`/`AmcService`/`WarrantyService`) |
| `kit_assignment_history` | M17 Asset Kits | **Still blocked** — `kit_assignments` / `kit_assignment_items` do not exist |

This plan is for review only — implementation happens in a separate session. Do not start
coding from this plan.

### Four pre-implementation decisions resolved with the owner

- **D14.1 — Report scope:** **two** reports, not one. Promote `maintenance` to enabled as
  *service history* (one row per `maintenance_records` row), and add a **new `amc_warranty`
  registry entry** for contract coverage and expiry. Rejected: a single entry with a
  `record_type` sub-filter, which would make the column set change per selection and break
  the one-heading-set-per-Export-class assumption every existing export relies on.
- **D14.2 — Cost:** **detail rows only.** Each maintenance record is a row with its own
  `cost`; no aggregate report and no totals footer. Keeps both reports plain Eloquent
  `Builder`s, so pagination, the 500-row queue threshold, and `ReportPdfExporter`'s generic
  table view all work unchanged.
- **D14.3 — End of Life:** `assets.is_eol` / `eol_projected_date` (shipped in M11) surface as
  **columns on the existing Asset Register report**, not as a new report. See the important
  cross-module consequence in step 5 — this one is not as contained as it looks.
- **D14.4 — Kit Assignment History:** **document, don't build.** The registry entry stays
  disabled and its `ReportComingSoonTest` row stays intact. Step 6 records the exact
  checklist M17 must complete so the report ships as part of that module.

---

## Schema

**No migrations.** Every table these reports read already exists:

| Table | Columns used |
|-------|-------------|
| `maintenance_records` | `asset_id`, `maintenance_type_id`, `status` (`scheduled\|in_progress\|completed\|cancelled`), `scheduled_date`, `performed_date`, `vendor`, `cost`, `description`, `is_capitalized`, `capitalized_amount`, `additional_useful_life_months`, `logged_by` |
| `amc_contracts` | `asset_id`, `vendor`, `start_date`, `end_date`, `coverage`, `cost`, `created_by` |
| `warranty_records` | `asset_id`, `provider`, `start_date` (nullable), `end_date`, `terms`, `created_by` |
| `assets` | `is_eol`, `eol_projected_date` (D14.3) |

---

## Build order

### 1. `ReportRegistry` — flip one entry, add one

```php
'maintenance' => [
    'label'       => 'Maintenance Report',
    'description' => 'Service history: preventive and corrective maintenance, vendor, and repair cost.',
    'enabled'     => true,        // was false
],
'amc_warranty' => [               // new
    'label'       => 'AMC & Warranty',
    'description' => 'AMC contracts and warranty records with coverage window and expiry status.',
    'enabled'     => true,
],
```

Update the class docblock, which currently says *"Maintenance needs M11, Kit Assignment
History needs M17"* — only the second clause survives.

### 2. `ReportService::buildMaintenanceQuery()` — the easy one

A plain `Builder` on `MaintenanceRecord`, structurally identical to `buildDisposalQuery()`:

```php
$query = MaintenanceRecord::query()->with([
    'asset.company', 'asset.category', 'maintenanceType', 'loggedBy',
]);

$this->scopeByRelatedAssetCompany($query, $this->intOrNull($filters, 'company_id'));
// maintenance_type_id, status, is_capitalized exact matches
// search -> whereHas('asset', tag/name like)
// applyDateRange($query, $filters, 'performed_date')
return $query->latest('id');
```

**Date column choice:** filter on `performed_date`, not `created_at`. Every other report
uses `created_at` because its domain has no separate business date, but a maintenance record
is scheduled on one date and performed on another, and "show me March's servicing" means
work *done* in March. Note that `performed_date` is null for `scheduled` rows, so a date
range implicitly excludes not-yet-performed work — correct for a service-history report, and
worth stating in the filter-bar label ("Performed between").

Register in both `queryFor()` and `exportFor()` match arms.

### 3. `amc_warranty` — one row per contract across two tables

This is the only structurally new thing in the plan, and it has a trap. Both tables hold
per-asset coverage contracts with near-identical semantics but different column names
(`vendor`/`provider`, `coverage`/`terms`, and `cost` on AMC only). `queryFor()` must return
an Eloquent `Builder` that `ReportController::show()` can `->paginate(20)`.

**Approach: a UNION with aliased columns and a `kind` discriminator.** Verified working
against real rows during planning — pagination, `orderBy`, and `with('asset.company')`
eager-loading all resolve correctly across the union.

```php
$amc = CoverageContract::query()->selectRaw(
    "id, asset_id, 'amc' as kind, vendor as provider_name, coverage as terms_text,
     start_date, end_date, cost"
);
$warranty = WarrantyRecord::query()->selectRaw(
    "id, asset_id, 'warranty' as kind, provider as provider_name, terms as terms_text,
     start_date, end_date, null as cost"
);
return $amc->union($warranty)->orderBy('end_date');
```

**New model `App\Models\Reports\CoverageContract`** (`$table = 'amc_contracts'`,
`$timestamps = false`, casts for `start_date`/`end_date`/`cost`, an `asset()` belongsTo).
It exists purely as the union's hydration target. Basing the union directly on
`App\Models\AmcContract` also works, but then every warranty row comes back as an
`AmcContract` instance — a lie that would mislead anyone reading the export's `map()`, and
one that silently inherits any cast later added to `AmcContract`. A purpose-built read-only
report model costs ~20 lines and removes both problems.

> #### ⚠ The trap: filters must be applied to BOTH legs of the union
>
> `->whereHas(...)` on the base builder before `->union()` constrains **only the first
> subquery**. Verified during planning: filtering to company "Acme" with the filter applied
> once returned 3 rows — Acme's AMC, Acme's warranty, **and another company's warranty**.
> That is a cross-company data leak in a system whose whole reporting layer is
> company-scoped.
>
> Every filter — `company_id`, `search`, date range, and the `kind`/status filters — must be
> applied to both legs. The plan's mitigation is a private helper that takes the filter
> closure and applies it to each leg, so the two can't drift:
>
> ```php
> private function applyCoverageFilters(Builder $q, array $filters): Builder
> ```
> called once per leg, rather than the `FiltersByCompany` trait helpers called once on the
> combined builder. **The trait's helpers each take a single `Builder` and are the natural
> thing to reach for here — that is exactly what produces the bug.** A test asserting the
> leak is closed is listed in the testing section and is the highest-value test in this plan.

**Derived `expiry_status`:** `expired` / `expiring` (≤30 days) / `active`, computed from
`end_date` in a `public static function expiryStatus(?Carbon $endDate): string` on
`ReportService`, mirroring the existing `ReportService::ageBucket()` precedent so the screen,
Excel, and PDF all read the same logic. The 30-day threshold matches M11's
`ExpiryAlertService::THRESHOLD_DAYS` first tier — reference it rather than re-hard-coding.

### 4. Exports, views, filter keys

Two new export classes, both copying `DisposalReportExport` exactly (`FromCollection`,
`WithHeadings`, `WithMapping`, a `rowCount()` the controller uses for the 500-row queue
threshold, and a constructor that runs the `ReportService` query so on-screen and exported
rows cannot diverge):

- `app/Exports/MaintenanceReportExport.php` — Asset Tag, Asset Name, Company, Type, Status,
  Scheduled, Performed, Vendor, Cost, Capitalized, Capitalized Amount, Extra Life (months),
  Logged By, Description
- `app/Exports/AmcWarrantyExport.php` — Asset Tag, Asset Name, Company, Kind, Provider/Vendor,
  Start, End, Days Remaining, Expiry Status, Cost, Coverage/Terms

Two new views under `resources/views/modules/reports/types/` (`maintenance.blade.php`,
`amc_warranty.blade.php`), both copying `disposal.blade.php`'s `<x-data-table :paginator>`
structure and using `<x-status-badge>` for status/expiry colour.

`ReportController::FILTER_KEYS` gains:

```php
'maintenance'  => ['company_id', 'maintenance_type_id', 'status', 'is_capitalized', 'date_from', 'date_to', 'search'],
'amc_warranty' => ['company_id', 'kind', 'expiry_status', 'date_from', 'date_to', 'search'],
```

`ReportController::lookups()` gains `maintenanceTypes` (`MaintenanceType::where('is_active', true)`),
and `show.blade.php`'s `@switch` gains a `@case` block per report.

### 5. EOL columns on Asset Register (D14.3) — larger blast radius than it looks

Adding `is_eol` / `eol_projected_date` to the Asset Register touches **three** places, not
one, because the report's screen and its export do not share a query:

1. `resources/views/modules/reports/types/asset_register.blade.php` — two new `<th>`/`<td>`
2. `app/Exports/AssetExport.php` — `CORE_HEADINGS` + `map()`
3. Nothing in `ReportService::buildAssetRegisterQuery()` (both columns are on `assets`
   already, so no new eager-load)

**The consequence to flag before starting:** `AssetExport` is *shared*. It backs M14's
`asset_register` report **and** M06's standalone asset export (`Assets\ExportController`,
`GenerateAssetExport`). Adding two columns therefore changes M06's export file layout too —
which is almost certainly desirable, but it is a change to a different module's user-facing
output and should be a deliberate call, not a surprise. It also means the M06 export tests
that assert on heading counts/positions need checking.

Worth recording separately: `AssetExport` maintains its own `buildQuery()` rather than
calling `ReportService::buildAssetRegisterQuery()`, so the two implementations of "the asset
register query" already exist side by side and differ (`AssetExport` supports
`show_deleted`; the report screen doesn't expose it). Unifying them is **out of scope here**
— flagged as pre-existing debt, not introduced by this work.

### 6. Kit Assignment History — the M17 checklist (D14.4)

Nothing is built. The registry entry and its `ReportComingSoonTest` row stay exactly as they
are. Record this checklist in `M17-asset-kits.md` so the report ships inside M17 rather than
becoming a second orphan:

1. `ReportRegistry`: `'kit_assignment_history' => 'enabled' => true` (+ drop the last clause
   of the class docblock)
2. `ReportService::buildKitAssignmentHistoryQuery()` + arms in `queryFor()` / `exportFor()`
3. `app/Exports/KitAssignmentHistoryExport.php`
4. `resources/views/modules/reports/types/kit_assignment_history.blade.php`
5. `ReportController::FILTER_KEYS['kit_assignment_history']` + any new `lookups()` entry
6. Remove `['kit_assignment_history']` from `ReportComingSoonTest::disabledTypes()` — at
   which point that provider has one row left and the test still guards `maintenance`… except
   `maintenance` is enabled by *this* plan, so **after this work the provider has only
   `kit_assignment_history` in it.** Do not delete the test; it is the guard that a disabled
   entry 404s rather than 500s.

M17 should reuse `AssetMovementBatch` for kit assignments (per M09's decisions-log entry), so
this report likely joins `kit_assignments` → `asset_movement_batches` → `asset_movements`
rather than reading a bespoke history table.

---

## Testing

New files in the existing `tests/Feature/Reports/` directory, matching its one-file-per-report
convention (`DisposalReportTest`, `AgingReportTest`, …).

| Test | Asserts |
|------|---------|
| `MaintenanceReportTest` | renders for `reports.view`; company filter scopes rows; `maintenance_type_id` / `status` / `is_capitalized` filters; `performed_date` range excludes `scheduled` rows with a null `performed_date`; Excel export headings + row count |
| `AmcWarrantyReportTest` | **both AMC and warranty rows appear in one result set** (the union works at all); `kind` filter isolates each; `expiry_status` buckets at the 30-day boundary; `days remaining` for an already-expired contract is negative/zero, not a crash |
| `AmcWarrantyCompanyScopeTest` | **the union filter leak.** Two companies each with an AMC *and* a warranty; filter to company A; assert exactly 2 rows and that company B's **warranty** specifically is absent. This is the one test that would have caught the bug found during planning, and it fails against the naive single-`whereHas` implementation |
| `ReportComingSoonTest` | **update, don't delete** — `disabledTypes()` drops `maintenance`, keeps `kit_assignment_history`; `test_index_renders_disabled_types_without_a_working_link` currently asserts `assertDontSee(route('reports.show', 'maintenance'))` and must flip to the kit report |
| `AssetRegisterReportTest` | extend with the two EOL columns |
| M06 export tests | check for heading-position assertions broken by the two new `AssetExport` columns |

Existing report tests must stay green untouched — that is the proof the registry/service/
controller contracts held. Expect **565 → roughly 585–595**.

---

## What this plan deliberately does not do

- **No aggregate/rollup reporting** (D14.2). No per-asset maintenance cost summary, no totals
  footer. `ReportPdfExporter`'s generic table view has no footer hook, and adding one is a
  change to shared export infrastructure for a single report's benefit.
- **No dedicated EOL report** (D14.3) — two columns on the Asset Register instead.
- **No kit assignment history** (D14.4) — blocked, documented for M17.
- **No unification of `AssetExport::buildQuery()` with `ReportService::buildAssetRegisterQuery()`.**
  Pre-existing duplication, out of scope, called out in step 5 so it isn't mistaken for new.
- **No dashboard changes.** M14's dashboard already surfaces an `inMaintenance` count via
  `DashboardService`; no maintenance-cost or expiry widget is added here.
- **No promotion of AMC/warranty into the M11 screens.** Those already have their own CRUD
  under `Maintenance\AmcController` / `WarrantyController`; this adds the *reporting* view.

## Acceptance criteria

| Criterion | Met by |
|-----------|--------|
| Maintenance Report enabled and exportable (BRD "Maintenance Reports") | Steps 1, 2, 4 |
| Repair cost visible per record (BRD "Repair Cost Tracking") | Step 2 `cost` column, D14.2 |
| AMC + warranty coverage reportable (BRD "AMC Tracking" / "Warranty Tracking") | Steps 1, 3, 4 |
| EOL visible in reporting (BRD "End of Life Tracking") | Step 5 |
| Exports match on-screen filters | Export classes run the same `ReportService` query — the invariant in `ReportService`'s docblock |
| Company scoping holds on every report | Step 3's both-legs helper + `AmcWarrantyCompanyScopeTest` |

---

## Docs to update on completion

- `CLAUDE.md` — M14 row: 9 reports → 11, drop the "Maintenance Report could be promoted in a
  follow-up" note, update the test count
- `docs/planning/MODULES_INDEX.md` — the M14 line about which reports shipped (line ~113)
- `docs/planning/modules/M14-reports-dashboard.md` — tick the report list items
- `docs/planning/modules/M17-asset-kits.md` — add step 6's checklist
- `docs/decisions-log.md` — D14.1–D14.4, plus **the union filter-leak finding**, which is the
  entry most likely to save someone later
