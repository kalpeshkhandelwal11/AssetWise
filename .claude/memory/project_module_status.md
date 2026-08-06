---
name: project-module-status
description: "AssetWise implementation progress — M00–M04 complete, M05 or M06 next"
metadata:
  type: project
---

M00, M01, M02, M03, and M04 are complete as of 2026-08-06 (all on the `daniels_branch` GitHub branch, kalpeshkhandelwal11/AssetWise). M05 (QR/Barcode) and M06 (Bulk Import/Export) are next — M06 depends on M04 (now done) in addition to M03; M05 depends only on M03. Both can proceed in parallel per `docs/planning/MODULES_INDEX.md`.

**What's in M01–M03 (done):** see prior summaries — auth/RBAC, shared masters (companies/locations/lookup tables), and full Asset Master (categories, asset CRUD, photos, attachments, location cascade API). `Asset::hasCustomFieldData()` was a stub in M03; M04 replaced it with a real check.

**What's in M04 (done) — Dynamic Fields / EAV custom fields per category:**
- Migrations: `category_fields`, `category_field_options`, `category_field_overrides`, `asset_field_values` (typed EAV columns: value_text/value_number/value_date/value_boolean)
- Models: `CategoryField`, `CategoryFieldOption` (no timestamps — deliberately cheap/rewritable rows), `CategoryFieldOverride`, `AssetFieldValue` (`categoryField()` relation uses `withTrashed()` since values must survive field soft-deletion)
- `App\Services\DynamicFieldService` (bound as a container singleton in `AppServiceProvider` for per-request memoization) — `resolveForCategory()` walks the category tree and merges `category_field_overrides` (recorded only on the leaf category, never intermediate ancestors), returns a `Collection<ResolvedField>` DTO (`app/Services/DynamicFields/ResolvedField.php`) rather than a plain array or decorated Eloquent model, specifically so no consumer can accidentally read a pre-override raw value
- `Admin\CategoryFieldController` (`/admin/categories/{cat}/fields`) enforces a cross-tree `field_key` uniqueness check (ancestors + descendants, not just the DB's same-category unique constraint) and blocks `field_type` changes once `asset_field_values` exist for that field
- `Admin\FieldOverrideController` — hide/relabel/change_required overrides; rejects overriding a category's own directly-defined field
- `Api\DynamicFieldController` at `/api/categories/{cat}/fields` (same `['web','auth']` middleware pattern as M03's `LocationCascadeController`) — returns field *definitions* only, never values
- `AssetController::store()/update()` run the core-field validator and `DynamicFieldService::validate()` together, merging both error bags into one `ValidationException` so a user sees a core-field error and a dynamic-field error in the same round trip (errors namespaced `fields.*`)
- Category lock is now real: `hasCustomFieldData()` → `fieldValues()->exists()`; locked users get `category_id` silently dropped (validated as `sometimes`, not `required`, when locked, mirroring the existing `company_id` pattern)
- Views: `x-dynamic-fields` anonymous Blade component (shares the parent form's Alpine `x-data` scope directly, no prop-passing) — fields/values embedded server-side on initial page load (zero fetches on first paint), AJAX only fires when the user changes the category dropdown
- 39 new tests (`tests/Unit/Services/DynamicFieldServiceTest.php` + 5 feature test files) — full suite: 205 passing

**Non-obvious things worth remembering if picking this back up:**
- Boolean dynamic fields need a hidden `value="0"` input paired with the checkbox (same name) since unchecked checkboxes don't submit — already implemented in `dynamic-fields.blade.php`.
- `AssetFieldValue`'s `decimal:4` cast always formats numbers with 4 fixed decimal places (e.g. "8.0000") — both `rawValue()` (edit-form prefill) and `displayValue()` (detail page) trim trailing zeros via a shared private helper; don't reintroduce the raw cast output directly into a view.
- `CategoryFieldOption` has no `timestamps()` column in its migration — the model needs `public $timestamps = false;` or inserts fail (`no column named updated_at`). This bit us once during M04 verification.

**Why M04 mattered:** it was the second cross-module contract owner (after M03's `Asset` model) — `DynamicFieldService::resolveForCategory()` must stay stable now that M06 (bulk import) and M14 (reports) will consume it.

**How to apply:** Pick M05 (`docs/planning/modules/M05-qr-barcode.md`, Phase 1 only — tag replacement needs M08 which doesn't exist yet) or M06 (`docs/planning/modules/M06-bulk-import-export.md`) next. Both are unblocked. Follow the same pattern used for M03/M04: read the module spec + `docs/decisions-log.md`'s "Pending Decisions" section for that module, resolve any open P#.# items with the user before finalizing a plan, then build in the established layering (thin controllers, `app/Services/`, `app/Policies/` only where instance-scoped abilities are needed).
