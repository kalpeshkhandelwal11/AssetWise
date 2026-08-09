# M06 — Bulk Import / Export

## Context

M00–M04 and M08 are built and merged on `daniels_branch` (307 tests passing). M06 depends only on M03 + M04 (both done) and is independent of M05 — the two modules share no files inside `app/`, only a handful of registration points (see the Parallel-track coordination note in the M05 plan). The scope draft at `docs/planning/modules/M06-bulk-import-export.md` needed no revisions during this planning session; this document formalizes it into an executable build plan, matching `M08-implementation-plan.md`'s depth.

This plan is for review only — implementation happens in a separate session. Do not start coding from this plan.

**Key constraint:** neither `app/Exports/`, `app/Imports/`, nor `app/Jobs/` exists anywhere in this codebase yet. `maatwebsite/excel` and `barryvdh/laravel-dompdf` are declared in `composer.json` but unconfigured (no `config/excel.php`, no `config/dompdf.php`), and no module has ever dispatched a queued job (`ShouldQueue`) before, despite `QUEUE_CONNECTION=database` already being the default. M06 is the first consumer of all three.

---

## Schema

New migrations after M05's range (`2026_08_10_1000xx`) — use date prefix `2026_08_11` so migration ordering can never collide with the M05 track regardless of authoring/merge order.

**`import_batches`** (`2026_08_11_100001`): `id, category_id (FK asset_categories, restrict), user_id (FK users, restrict), filename (string), status (enum: processing,completed,failed, default processing), total_rows (unsigned int, default 0), success_count (unsigned int, default 0), error_count (unsigned int, default 0), timestamps`.

**`import_batch_rows`** (`2026_08_11_100002`): `id, batch_id (FK import_batches, cascade), row_number (unsigned int), status (enum: success,failed), errors (json, nullable), asset_id (nullable FK assets, nullOnDelete), timestamps`. Index on `(batch_id, status)` for the batch status/error report view.

No new export-side table — reuse the existing, currently-unused `ExportLog` model (`user_id, report_type, filters json, row_count, file_name, created_at`, no `updated_at`) and its static `ExportLog::record(string $reportType, array $filters, ?int $rowCount, ?string $fileName)` helper. It was clearly scaffolded in anticipation of this module (`User::exportLogs(): HasMany` already exists, nothing calls `record()` yet).

---

## Import mechanics

`app/Imports/AssetImport.php` (Maatwebsite Excel `ToCollection` or `OnEachRow` import class — `OnEachRow` preferred so row numbers and partial failures are trivially tracked). Per row:

1. Resolve `company_code` → `company_id` via `Company::where('code', $row['company_code'])->first()` — the same lookup shape `AssetController`'s create/edit dropdowns already use for `company_id`, just by code instead of id since a spreadsheet can't reference a database id.
2. Validate core columns with a plain `validator()` call (mirrors the core-fields half of `AssetController::validateAsset()`).
3. Resolve the row's category, call `DynamicFieldService::resolveForCategory($categoryId)`, then `DynamicFieldService::validate($input, $fields)` for the custom columns.
4. **Merge both error bags into one** — this is the exact pattern `AssetController::validateAsset()` already uses to surface core + dynamic-field errors together; reuse it rather than inventing a second merge strategy for import rows.
5. On success: `AssetService::create()` + `DynamicFieldService::saveValues()` (identical calls a normal single-asset create makes), write an `import_batch_rows` row with `status=success` and the new `asset_id`.
6. On failure: write `import_batch_rows` with `status=failed` and the collected `errors` json — **the row is skipped, the batch continues** (no silent partial failures, but also no whole-batch abort on a single bad row, per the draft's acceptance criteria: "Invalid rows reported; valid rows imported atomically per row").

`app/Exports/AssetTemplateExport.php` (Maatwebsite `FromArray`/`WithHeadings`) — per-category template built directly from `DynamicFieldService::resolveForCategory($id)`: core columns (`name, serial_number, model, manufacturer, ...`) + `company_code` + one column per resolved field (`fieldKey` as header) in `display_order`. Template must match the category's field schema exactly, including inherited fields — reuse `resolveForCategory()` as the single source of truth rather than re-deriving column lists elsewhere.

`app/Exports/AssetExport.php` — filtered export honoring the exact same filter set `AssetController::index()` already applies (including `company_id`), plus a company name/code column and each row's own category's resolved custom columns.

---

## Queueing — first use of `app/Jobs/`

Per CLAUDE.md, heavy exports run as queued jobs with a download-link notification; `QUEUE_CONNECTION=database` is already the configured default (shared-hosting, no Redis), so no infra change is needed — just the first `ShouldQueue` job classes.

- `app/Jobs/ProcessAssetImport.php` — wraps the `AssetImport` run for a given `import_batches` row: dispatched from `Assets\ImportController::store()` after the batch row is created with `status=processing`; on completion updates `total_rows`/`success_count`/`error_count`/`status`, then `NotificationService::send($user, 'import_completed', ['batch_id' => ..., 'success_count' => ..., 'error_count' => ...])` — reusing the M12 stub exactly as documented (`send(User $user, string $type, array $data)`), no changes to that service.
- `app/Jobs/GenerateAssetExport.php` — generates the export file to `storage/app/exports/` (via the existing `storage:link`), calls `ExportLog::record(...)`, then `NotificationService::send($user, 'export_ready', ['download_url' => ..., 'row_count' => ...])`. Small/synchronous exports (below a row-count threshold, e.g. 500) may run inline in the controller instead of queuing — the draft doesn't mandate queuing for every export, only "heavy" ones per CLAUDE.md.

---

## Controllers, routes, permissions

`Assets\ImportController` — `index()` (import wizard entry), `template(AssetCategory $category)` (streams the generated template), `store()` (validates the uploaded file + selected category, creates the `import_batches` row, dispatches `ProcessAssetImport`), `show(ImportBatch $batch)` (status + per-row error report, matching the draft's `GET /assets/import/{batch}`).

`Assets\ExportController` — `index()` (filter form, reusing `AssetController`'s existing filter whitelist), `store()` (dispatches `GenerateAssetExport` or streams inline for small result sets).

Both sit under the existing `assets.` route group in `routes/web.php`, matching `AssetController`'s thin-controller/service-delegation convention — no changes to `AssetController`, `AssetService`, or `DynamicFieldService` themselves.

```php
Route::get('assets/import', [Assets\ImportController::class, 'index'])->name('assets.import.index');
Route::get('assets/import/template/{category}', [Assets\ImportController::class, 'template'])->name('assets.import.template');
Route::post('assets/import', [Assets\ImportController::class, 'store'])->name('assets.import.store');
Route::get('assets/import/{batch}', [Assets\ImportController::class, 'show'])->name('assets.import.show');
Route::get('assets/export', [Assets\ExportController::class, 'index'])->name('assets.export.index');
Route::post('assets/export', [Assets\ExportController::class, 'store'])->name('assets.export.store');
```

**Permissions**: `imports.manage`, `assets.bulk`, `assets.export` are **already seeded** in `RolePermissionSeeder` and granted to Super Admin + Asset Manager — confirmed unused by any existing controller, evidently scaffolded in anticipation of this exact module. No seeder changes needed for M06.

---

## Tests

- `tests/Unit/Imports/AssetImportTest.php` — valid rows create real `Asset` + `asset_field_values` rows; an invalid `company_code` and a missing required dynamic field are both reported on their specific row without aborting the rest of the batch; row-level error messages are specific enough to act on (field name + reason, not a generic "row failed").
- `tests/Feature/Assets/ImportControllerTest.php` — permission gating (`imports.manage`/`assets.bulk`); template download matches the target category's resolved field schema exactly (including inherited fields from ancestor categories); `Queue::fake()` + assert `ProcessAssetImport` is dispatched with the right batch id.
- `tests/Feature/Assets/ExportControllerTest.php` — permission gating (`assets.export`); export respects the same filters as the asset list (including `company_id`); output includes company name/code and per-row category custom columns; `ExportLog::record()` is called with a correct row count.
- `tests/Feature/Jobs/ProcessAssetImportTest.php` — end-to-end job run updates `import_batches` counts correctly and calls `NotificationService::send()` with type `import_completed` (assert via the `notifications` table, per `NotificationService`'s existing stub behavior — it writes `GenericNotification` with the app-level `$type` string, not the PHP class name).

---

## Verification

- `php artisan migrate:fresh --seed` runs clean with the 2 new tables.
- `php artisan test` — full suite green, no regressions to the existing 307 tests.
- Manual smoke: download a category template, fill a few rows including one with a bad `company_code` and one missing a required custom field, upload it, confirm the batch report shows the exact failing rows with reasons and that the valid rows created real assets (visible in the normal asset list); run a filtered export (e.g. by company) and confirm the downloaded file opens in Excel with the company column, resolved custom columns, and only the filtered rows present.
