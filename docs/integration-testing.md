# Integration Testing Notes

Record of the first full end-to-end integration pass over the running application
(2026-08-13), covering M01–M09 and M13. Everything below was executed against a real
`php artisan serve` instance driven through Chrome, not through the phpunit HTTP kernel.

> **Why this document exists.** The suite was 390/390 green when this pass started, and it
> still found six defects — four of them user-blocking. Every one lived in territory the
> feature tests structurally cannot reach: they POST directly to endpoints and assert on
> the database, so they never render a Blade view, never boot Alpine, and never click
> anything. Treat "tests pass" and "the app works" as separate claims.

---

## How to reproduce this pass

```powershell
php artisan migrate:fresh --seed        # rebuild the dev database
npm run build                           # compile assets (no stale public/hot)
php artisan serve --host=127.0.0.1 --port=8123
```

Then log in as `admin@assetwise.test` / `Admin@1234`.

**First-login gotcha:** `AdminUserSeeder` sets `must_change_password = true`, so the very
first login redirects to `/password/change` before anything else is reachable. That is M01
working as designed, but it is not mentioned in the README quickstart. Either set a new
password there, or clear the flag for a throwaway dev box:

```powershell
php artisan tinker --execute="App\Models\User::where('email','admin@assetwise.test')->update(['must_change_password' => false]);"
```

---

## Flows verified end to end

| Flow | Modules exercised | Result |
|------|-------------------|--------|
| Create asset via wizard | M02, M03, M04 | ✅ |
| Generate tag batch, list pool | M05 | ✅ |
| Print label sheets (PDF + Word) | M05 | ✅ valid `%PDF-1.7` / `PK` zip payloads, correct MIME + filename |
| Per-category import template download | M06 | ✅ valid `.xlsx` |
| Filtered export (inline path, <500 rows) | M06 | ✅ valid `.xlsx`, `ExportLog` row written |
| Single movement → 2-level approval → applied | M09 → M08 → M09 listener | ✅ custodian applied on terminal step only |
| Bulk multi-select → one batch approval → `applyBulk` | M09 (P9.1) → M08 | ✅ both assets updated atomically |
| Disposal → approve → write-off → scrap | M13 → M08 → M13 | ✅ asset set to Disposed, value recorded |
| Disposal workflow created through the admin UI | M08 | ✅ proves "configurable without code changes" |
| Disposed asset rejected for movement | M09 ↔ M13 guard | ✅ blocked with the correct message |
| Status ledger written on scrap | M13 → M09's `asset_status_histories` | ✅ `Available → Disposed` ("Disposal scrapped") |
| Audit trail + notifications | M01, M12 stub | ✅ `activity_log` and `notifications` both fed |

All 30 authenticated GET routes returned 200/302 — no 500s anywhere.

---

## Defects found and fixed

### 1. Movement wizard was completely non-functional in a browser — `@json` comma gotcha

**Severity: blocking.** Selecting any movement type revealed no fields, so no movement
could be submitted through the UI at all.

Blade's `@json` compiles via `explode(',', $expression)` and treats everything after the
first comma as the *flags* argument
([`CompilesJson.php:22`](../vendor/laravel/framework/src/Illuminate/View/Compilers/Concerns/CompilesJson.php)).
So `@json($movementTypes->pluck('code', 'id'))` compiled to
`json_encode($movementTypes->pluck('code', 'id'), 512)` — flags `512` instead of the
default `15`, dropping `JSON_HEX_QUOT`. The raw `"` characters then terminated the
enclosing `x-data="..."` attribute, and Alpine died with
`Alpine Expression Error: Unexpected token ';'`, taking every `x-show` on the form with it.

**Fix:** use `@js()` in `movements/create.blade.php` and `movements/bulk-create.blade.php`.
`@js` passes the whole expression to `Js::from()` without comma-splitting and always
applies the required escaping flags.

> **Rule for this codebase:** never use `@json(...)` with a comma inside an HTML attribute.
> Use `@js(...)` for anything embedded in `x-data` / `@click` / other Alpine attributes.
> `{{ json_encode(...) }}` is also safe (Blade's `e()` escapes the quotes to `&quot;`), which
> is why the existing `admin/masters/index.blade.php` modal was unaffected.

### 2. No user could approve anything on a fresh install — seeder inconsistency

**Severity: blocking.** Every movement, disposal and tag replacement sat pending forever.

`AdminUserSeeder` created exactly one user holding only `Super Admin`, while
`WorkflowSeeder` routes the default chains to `Approver` / `Asset Manager`. Approver
eligibility is matched on Spatie **role**
(`WorkflowService::matchesStep()` → `$user->hasRole(...)`), not on permissions — and
`Super Admin` is a role, not a wildcard. So the only seeded account matched no step of any
seeded workflow, and "My Inbox" was permanently empty.

**Fix:** `AdminUserSeeder` now grants `['Super Admin', 'Asset Manager', 'Approver']`.

The engine was deliberately left alone. Making `workflow.manage` a universal override
would have dissolved M08's role-based separation of duties, which is a real feature —
an organisation may legitimately want Super Admin ≠ Approver. The seed data was what was
inconsistent, not the engine.

### 3. Workflow misconfiguration failed silently on every submission form

**Severity: high.** Submitting a disposal on a fresh install re-rendered the form with no
message whatsoever — indistinguishable from a dead button.

`WorkflowService` reports all three configuration failures under the error key `workflow`,
which matches no field on any of the three consumer forms, so `x-input-error` never
displayed it. This is not an edge case: `disposal` ships with **no** seeded workflow by
design (M08), so it is the expected first-run experience for that module.

**Fix:** added an `@error('workflow')` banner to `movements/create`,
`movements/bulk-create` and `disposals/create`, naming the module and pointing at
Administration → Workflows.

### 4. Bulk movements were invisible in Movement History

**Severity: high.** A bulk submission redirected to a list that did not contain it.

`MovementController::index()` filtered `whereNull('batch_id')` on the assumption — stated
in its own comment — that the batch would appear as its own row. That row was never
implemented, so batch members were hidden and nothing replaced them.

**Fix:** dropped the filter so every row is one asset actually moving, and added a "Bulk"
badge on batch members to preserve the grouping signal.

### 5. Alpine version mismatch

`alpinejs@3.15.12` was running with `@alpinejs/collapse@3.16.0`. Aligned both to `3.16.0`.

### 6. `x-collapse` used without the plugin

Every page logged seven `Alpine Warning: You can't use [x-collapse] without first
installing the "Collapse" plugin` warnings — the sidebar submenus and the locations tree
snapped open with no animation. `@alpinejs/collapse` is now installed and registered in
`resources/js/app.js`. Console is clean after the fix.

### Also changed: transactional submissions

`DisposalService::submit()` and `MovementService::submit()` now wrap row creation and
`WorkflowService::submit()` in a single transaction. Without it, a submission attempted
while no active workflow exists left an orphaned `pending_approval` row, which then
permanently tripped the `hasPendingMovement()` / `hasPendingDisposal()` guard and locked
the asset out of ever being moved or disposed. Verified: 0 orphaned rows after a failed
disposal submit.

### Investigated, not a defect

The `x-confirm-modal` panel looked translucent with page text bleeding through — that was
a screenshot captured mid-transition. Zooming after the transition settled showed a fully
opaque panel. Left alone.

---

## Regression coverage added

`tests/Feature/Movement/MovementViewRegressionTest.php` — four tests that render the pages
the browser pass exposed:

- both wizards embed the type map without raw quotes (asserts `JSON.parse(` is present and
  `typeCodes: {"` is not) — this is the assertion that would have caught defect 1
- Movement History lists bulk batch members — catches defect 4
- the missing-workflow message is actually visible on the re-rendered form — catches defect 3

The `x-transition` and Alpine-plugin issues (5, 6) remain browser-only; phpunit cannot
observe them. A headless-browser layer (Dusk/Playwright) would be the honest way to cover
that class, and is the main gap left in this project's testing strategy.

---

## Known cosmetic issues (not fixed)

| Issue | Where | Note |
|-------|-------|------|
| Page title reads "Laravel" | `layouts/app.blade.php` `<title>` | `@yield('title', ...)` is never populated; pages set `page-title` instead, which only drives the in-page heading |
| Tag code type renders "Qr" | `admin/tags/index.blade.php` | `ucfirst('qr')`; should be "QR" |
| No `x-transition` on the bulk bar | `modules/assets/index.blade.php` | Deliberate — Alpine's JS transition applied a stale state to this fixed-position element, leaving the bar hidden while assets were selected. Plain `x-show` is reliable; see the comment in the view |
