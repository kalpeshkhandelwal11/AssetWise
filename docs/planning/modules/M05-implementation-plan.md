# M05 — QR / Barcode Tag Pool

## Context

M00–M04 and M08 are built and merged on `daniels_branch` (307 tests passing). M05 depends only on M03 (done); its Phase 2 replacement flow additionally depends on M08 (also done — `tag_replacement` is already registered in `ApprovalWorkflow::MODULES` and `WorkflowSeeder` ships a default two-level chain for it). **M05 is the first real consumer of the M08 approval engine** — no domain module has wired a listener onto `ApprovalRequestApproved` yet, so this plan follows the contract documented in that event's class docblock and `ApprovalRequest::getApprovableLabelAttribute()`'s forward-compat hook, rather than copying an existing example.

This plan is for review only — implementation happens in a separate session. Do not start coding from this plan.

Four decisions were resolved with the user before finalizing this plan, revising the original `docs/planning/modules/M05-qr-barcode.md` draft (which used a free-text batch prefix):

- **Tag numbering is category-independent** — a purely global, sequential, non-repeating numeric ID. No category prefix, no assignment restriction by category.
- **Tag assignment happens after asset creation**, from a pre-generated pool — matches the architecture CLAUDE.md already documents; no change from the original draft.
- **QR vs. Barcode is a single global admin setting**, not chosen per batch. No settings mechanism exists anywhere in this codebase yet, so this module introduces a small, deliberately generic key-value `settings` table rather than a one-off M05-only table.
- **Printable tag sheets download as both PDF and Word.** DomPDF is already a composer dependency (unused so far, no published config); Word requires a **new** dependency, `phpoffice/phpword`.

---

## Schema

New migrations after M08's (last existing: `2026_08_09_100002_add_org_fields_to_users_table.php`). Use date prefix `2026_08_10` — a range not used by any other in-flight track (M06 uses `2026_08_11`), so migration ordering never collides regardless of which track is authored/merged first.

**`settings`** (`2026_08_10_100001`): `id, key (string 100, unique), value (text, nullable), timestamps`. Deliberately generic — not `tag_settings` — so later modules can reuse it for their own admin-configurable toggles instead of each inventing a bespoke table.

**`tag_batches`** (`2026_08_10_100002`): `id, quantity (unsigned int), code_type (enum: qr,barcode), created_by (FK users, restrict), timestamps`. `code_type` is a **snapshot** of the `settings.tag_code_type` value at generation time — if an admin flips the global setting later, already-printed batches must not retroactively relabel.

**`tags`** (`2026_08_10_100003`): `id, tag_number (string 20, unique, nullable at the DB level), batch_id (FK tag_batches, restrict), code_type (enum qr,barcode), qr_payload (string, nullable), barcode_value (string, nullable), status (enum: available,assigned,inactive, default available), timestamps`. Index on `status` (pool-list filtering is the hottest query). `tag_number` is nullable only so `TagService::generateBatch()` can insert the row first and fill in the number from the row's own auto-increment id in the same transaction — every committed row ends up with a non-null number in practice. No soft-deletes: CLAUDE.md is explicit tag numbers are never reused, and `inactive` status already models retirement — nothing is ever deleted.

**`asset_tag_assignments`** (`2026_08_10_100004`): `id, asset_id (FK assets, cascade), tag_id (FK tags, restrict), status (enum active,inactive, default active), assigned_by (FK users, restrict), assigned_at (timestamp), deactivated_by (nullable FK users), deactivated_at (nullable timestamp), timestamps`. "Only one active assignment per asset" is enforced in `TagService`, not a DB constraint — MySQL has no clean partial-unique-index story here; this mirrors how `WorkflowService::activate()` enforces "one active workflow per module" in application code rather than schema.

**`scan_logs`** (`2026_08_10_100005`): `id, tag_id (FK tags, cascade), asset_id (nullable FK assets, nullOnDelete), user_id (FK users, restrict), method (enum: camera,manual), scanned_at (timestamp, useCurrent)`. `user_id` is required, not nullable — every route in this app requires auth (no public routes exist anywhere else), so an unauthenticated scan is not a case this schema needs to model.

**`tag_replacement_requests`** (`2026_08_10_100006`, Phase 2): `id, asset_id (FK assets, restrict), current_tag_id (FK tags, restrict), new_tag_id (FK tags, restrict), reason (text), status (enum pending,approved,rejected, default pending), approval_request_id (nullable FK approval_requests, nullOnDelete), requested_by (FK users, restrict), timestamps`.

---

## Models

`Setting` (`app/Models/Setting.php`) — static helpers, no per-instance ceremony needed for a handful of global keys:
```php
public static function get(string $key, ?string $default = null): ?string
public static function set(string $key, string $value): void   // updateOrCreate(['key' => $key], ['value' => $value])
```

`TagBatch` (`tags()` hasMany), `Tag` (`batch()` belongsTo, `assignments()` hasMany, `activeAssignment()` — latest `assignments()` where `status='active'`), `AssetTagAssignment` (`asset()`, `tag()` belongsTo), `ScanLog` (`tag()`, `asset()`, `user()`), `TagReplacementRequest` (Phase 2 — `asset()`, `currentTag()`, `newTag()`, `approvalRequest()`; implements `getApprovalLabel(): string` returning e.g. `"Tag Replacement — {$this->asset->name}"`, per `ApprovalRequest`'s documented forward-compat hook).

`Asset` additions (the existing M05 draft's handoff note already calls for these): `tagAssignments()` hasMany, `activeTag()` — a relation/accessor resolving through the current active `AssetTagAssignment` to its `Tag`. Verify during implementation whether `AssetService`/`AssetController` has any auto-tag-on-create logic to remove — the M03 investigation for this plan found `asset_tag` is currently just a plain free-text fillable column with no generation logic, so this is likely a no-op confirmation, not an actual removal.

---

## `App\Services\TagService`

```php
generateBatch(int $quantity, User $actor): TagBatch
assignToAsset(Tag $tag, Asset $asset, User $actor): AssetTagAssignment
requestReplacement(Asset $asset, Tag $newTag, string $reason, User $actor): TagReplacementRequest   // Phase 2
applyReplacement(TagReplacementRequest $request): void   // called by the ApprovalRequestApproved listener
resolveScan(string $tagNumber): App\Services\Tags\ScanResult   // readonly DTO, mirrors M04's ResolvedField pattern
logScan(Tag $tag, ?Asset $asset, User $user, string $method): ScanLog
```

**`generateBatch()`** — the core of the revised tag-numbering design:
1. Read `Setting::get('tag_code_type', 'qr')`.
2. `DB::transaction()`:
   - Create the `TagBatch` row, snapshotting `code_type`.
   - Loop `$quantity` times: insert a bare `Tag` row (`batch_id`, `code_type`, `status='available'`, `tag_number=null`), then immediately `update(['tag_number' => str_pad((string) $tag->id, 6, '0', STR_PAD_LEFT)])` — using the row's **own** auto-increment id as the numbering source. This is deliberately simple: MySQL's `AUTO_INCREMENT` already guarantees global, monotonic, gap-tolerant-but-never-reused numbers, and since tags are never hard-deleted (only marked `inactive`), the id — and therefore the tag number — is never reused either. No separate counter table, no `lockForUpdate()` row-locking needed.
   - Set `qr_payload = url("/scan/{$tagNumber}")` when `code_type === 'qr'`, or `barcode_value = $tagNumber` when `code_type === 'barcode'` (never both — a tag is one or the other, decided by the batch's snapshotted setting).
3. Return the `TagBatch` with `tags` eager-loaded.

**`assignToAsset()`** — inside a transaction: guard the target asset has no existing `active` assignment (throw `ValidationException` if it does — replacement, not reassignment, is the path for changing an already-tagged asset), create the `AssetTagAssignment`, flip `Tag.status` to `assigned`, sync `assets.asset_tag` to the new tag number.

**`resolveScan()`** — look up by `tag_number`; return `ScanResult{status: 'assigned'|'available'|'inactive', tag: Tag, asset: ?Asset}` per the routing table already in the M05 draft (`assigned` → asset detail, `available` → assign prompt, `inactive` → retired message + link to the asset's current tag if any).

**`applyReplacement()`** (Phase 2) — deactivate the current `AssetTagAssignment`, create a new one for `new_tag_id`, flip `current_tag_id` to `inactive` and `new_tag_id` to `assigned`, re-sync `assets.asset_tag`.

---

## Label rendering — PDF + Word

New `app/Services/Tags/TagLabelRenderer.php`: given a `Collection<Tag>`, generates one image per tag — `SimpleSoftwareIO\QrCode\Facades\QrCode::size(150)->generate($tag->qr_payload)` when `code_type='qr'`, or `Picqer\Barcode\BarcodeGeneratorPNG` (`getBarcode($tag->barcode_value, self::TYPE_CODE_128)`) when `code_type='barcode'`, base64-encoded for embedding.

- **PDF**: `resources/views/admin/tags/print-pdf.blade.php` — a grid of `<img>` + tag-number captions — rendered via `Pdf::loadView('admin.tags.print-pdf', [...])->download("tags-{$identifier}.pdf")`. `barryvdh/laravel-dompdf` is already a composer dependency but has no published config yet — run `php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"` as part of implementation.
- **Word**: new `app/Services/Tags/TagLabelWordExport.php` using `PhpOffice\PhpWord\PhpWord` — same image set laid out in a table via `Section::addTable()`/`addImage()`, streamed as a `.docx` (`IOFactory::createWriter($phpWord, 'Word2007')->save('php://output')` inside a `StreamedResponse`). **Requires `composer require phpoffice/phpword`** — confirmed absent from `composer.json`; this is the only new package this plan introduces (every other package M05 needs — `simplesoftwareio/simple-qrcode`, `picqer/php-barcode-generator`, `barryvdh/laravel-dompdf` — is already declared).

Both PDF and Word endpoints accept either a `tag_batches` id or an explicit `tag_ids[]` list, so a batch's still-available tags can be reprinted later without regenerating new ones.

---

## Controllers, routes, permissions

- **`Admin\SettingController`** — `edit()` (shows current `tag_code_type`), `update()` (validates `in:qr,barcode`, calls `Setting::set()`). New permission: `settings.manage`.
- **`Admin\TagBatchController`** — `index()` (pool list, `status` filter), `create()`/`store()` (quantity only — no prefix or category input).
- **`Admin\TagPrintController`** — `pdf()`, `word()` — both accept `batch` (id) or `tag_ids[]`.
- **`Assets\TagController`** — `assign()`/`store()` (available-tag picker + scan-to-assign input), and Phase 2: `replaceForm()`/`submitReplacement()` (submits via `WorkflowService::submit($replacementRequest, 'tag_replacement', $actor)`).
- **`Api\ScanController`** — `resolve($tagNumber)` for `GET /scan/{tag_number}` (web-session authenticated, matching every other route in this app), `log()` for `POST /api/scan` (mobile scan logging, under the existing `web`+`auth` group in `routes/api.php` alongside the other Alpine.js JSON helpers).

Routes register under the existing `admin.`/`assets.` prefix groups in `routes/web.php`:
```php
Route::prefix('admin/tags')->name('admin.tags.')->group(function () {
    Route::get('/', [Admin\TagBatchController::class, 'index'])->name('index');
    Route::get('/batches/create', [Admin\TagBatchController::class, 'create'])->name('batches.create');
    Route::post('/batches', [Admin\TagBatchController::class, 'store'])->name('batches.store');
    Route::get('/print/pdf', [Admin\TagPrintController::class, 'pdf'])->name('print.pdf');
    Route::get('/print/word', [Admin\TagPrintController::class, 'word'])->name('print.word');
});
Route::get('/admin/settings/tags', [Admin\SettingController::class, 'edit'])->name('admin.settings.tags.edit');
Route::patch('/admin/settings/tags', [Admin\SettingController::class, 'update'])->name('admin.settings.tags.update');

Route::post('assets/{asset}/tags/assign', [Assets\TagController::class, 'store'])->name('assets.tags.assign');
Route::get('assets/{asset}/tags/replace', [Assets\TagController::class, 'replaceForm'])->name('assets.tags.replace');
Route::post('assets/{asset}/tags/replace', [Assets\TagController::class, 'submitReplacement'])->name('assets.tags.replace.submit');

Route::get('/scan/{tag_number}', [Api\ScanController::class, 'resolve'])->name('scan.resolve');
```
`routes/api.php`: `Route::post('/scan', [Api\ScanController::class, 'log'])->name('api.scan.log');`

**Permissions** (`RolePermissionSeeder`): `tags.generate`, `tags.assign`, `tags.view`, `tags.print`, `tags.replace`, `settings.manage`. Super Admin gets everything automatically (existing `syncPermissions` pattern). Grant the full `tags.*` set to Asset Manager, matching how M08 granted it `workflow.approve` — Asset Manager is this app's operational-ownership role for physical assets.

---

## Phase 2 — approval integration (no M08 changes needed)

`tag_replacement` is already in `ApprovalWorkflow::MODULES` (DB enum too) and `WorkflowSeeder` already ships a default two-level chain (Asset Manager → Super Admin, 48h escalation each) — no schema or seeder change required in M08 itself.

New `app/Listeners/ApplyTagReplacement.php`, auto-discovered via its typed `handle(ApprovalRequestApproved $event)` parameter — **never** also register it in `AppServiceProvider::boot()`, the same double-registration mistake CLAUDE.md flags for `LogSuccessfulLogin`/`LogFailedLogin` applies identically here. Body: `if ($event->request->workflow->module !== 'tag_replacement') return;` then `app(TagService::class)->applyReplacement($event->request->approvable);`.

`Assets\TagController::submitReplacement()` creates the `TagReplacementRequest` row via `TagService::requestReplacement()`, then calls `WorkflowService::submit($replacementRequest, 'tag_replacement', $actor)` and stores the resulting `approval_request_id` back onto the request row.

---

## Tests

Follow existing conventions: `RefreshDatabase` + `Tests\Concerns\SeedsRolesAndPermissions`, `createUserWithRole()`, bare `User::factory()->create()` + `assertForbidden()` for permission-denial cases, `givePermissionTo([...])` for the allowed path.

- `tests/Unit/Services/TagServiceTest.php` — `generateBatch()` produces sequential, non-repeating `tag_number`s regardless of any asset category context; QR vs. barcode fields populate correctly per the current `tag_code_type` setting, and changing the setting mid-stream doesn't relabel already-generated batches; `assignToAsset()` rejects a second active assignment on the same asset; `resolveScan()` returns the correct status for all three tag states.
- `tests/Feature/Admin/TagBatchControllerTest.php`, `SettingControllerTest.php`, `TagPrintControllerTest.php` — permission gating (`tags.generate`, `settings.manage`, `tags.print`); PDF/Word downloads return the expected content-type and a non-empty body.
- `tests/Feature/Assets/TagAssignmentTest.php` — assign flow, `assets.asset_tag` sync, tag history listing on asset detail.
- `tests/Feature/Api/ScanControllerTest.php` — all three scan outcomes; `POST /api/scan` writes a `scan_logs` row.
- `tests/Feature/Approvals/TagReplacementApprovalTest.php` — submit → approve through both workflow levels → `ApplyTagReplacement` fires → old tag scans `inactive`, new tag scans `assigned`. Use `Event::fake([ApprovalRequestApproved::class])` (partial fake, not a bare `Event::fake()`) since a full fake also swallows the Eloquent model events `LogsActivity` depends on — same pattern CLAUDE.md documents for M08's own approval tests.

---

## Verification

- `php artisan migrate:fresh --seed` runs clean with the 6 new tables (5 Phase 1 + 1 Phase 2) and `SettingsSeeder`'s default `tag_code_type=qr` row.
- `php artisan test` — full suite green, no regressions to the existing 307 tests.
- Manual smoke (`claude-in-chrome` or local browser): generate a batch of 5 tags, confirm sequential non-repeating tag numbers; toggle the admin QR/barcode setting, generate a second batch, confirm its `code_type` differs from the first; download both PDF and Word label sheets for a batch and confirm they open with correctly rendered images; create an asset, assign an available tag to it, scan `/scan/{tag_number}`, confirm it resolves to that asset; submit a tag replacement, approve through both approval levels as the respective role-holders, confirm the old tag now scans `inactive` (with a link to the current tag) and the new tag scans `assigned`.
