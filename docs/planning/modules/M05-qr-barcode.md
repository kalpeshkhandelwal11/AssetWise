# M05 — QR / Barcode (Tag Pool Model)

| | |
|--|--|
| **Developer** | Dev 1 (Phase 1) + Dev 2 (replacement approval, Phase 2) |
| **Phase** | 1 (pool/assign/scan) + 2 (replacement) |
| **Depends on** | M03; tag replacement also needs M08 |
| **Blocks** | M10 |
| **Parallel with** | M04, M11 |

> **M08 is built — the replacement flow is unblocked.** `tag_replacement` is already in the `approval_workflows.module` enum and `WorkflowSeeder` ships a default chain (Asset Manager → Super Admin, 48h escalation).
>
> Submit with `WorkflowService::submit($replacement, 'tag_replacement', $actor)`. Do **not** expect M08 to call `TagService::applyReplacement()` — it cannot, it has no compile-time knowledge of M05. Instead add a listener in `app/Listeners/` for `App\Events\ApprovalRequestApproved` (auto-discovered via the `handle()` type-hint — never also register it in `AppServiceProvider`), filter on `$event->request->workflow->module === 'tag_replacement'`, and apply the change to `$event->request->approvable`. That event fires only on the terminal step, synchronously.
>
> Optionally implement `getApprovalLabel(): string` on the replacement model — the approver inbox picks it up automatically and falls back to `ClassName #id` otherwise.

## Scope

**Pre-generate** QR/barcode labels into an inventory pool → print → **assign to assets later** (after the asset already exists — assets are never auto-tagged on creation). When a label must change, assign a **new tag from pool**, old tag becomes **inactive** — replacement goes through **approval workflow** (Phase 2).

**Tag numbering:** tag numbers are a purely global, sequential, non-repeating numeric ID (e.g. `000001`, `000002`, …) — **not** category-based. No prefix, no per-category pool, no assignment restriction by category. Which code type (QR or barcode) gets generated is controlled by a single **global admin setting**, not chosen per batch — see `settings` table below.

## DB Tables

- `settings` (generic key-value: `key` unique, `value`) — new, reusable beyond M05; first row is `tag_code_type` (`qr` or `barcode`), admin-editable.
- `tag_batches` (quantity, `code_type` — snapshot of the `tag_code_type` setting at generation time, created_by)
- `tags` (tag_number unique — global sequential, derived from the row's own auto-increment id, never reused; `code_type`; qr_payload; barcode_value; status: available/assigned/inactive)
- `asset_tag_assignments` (asset_id, tag_id, status: active/inactive, assigned/deactivated metadata)
- `tag_replacement_requests` (Phase 2 — asset_id, current_tag_id, new_tag_id, reason, status, approval_request_id)
- `scan_logs` (tag_id, asset_id nullable, user_id, method, scanned_at)

## Packages

- `simplesoftwareio/simple-qrcode` (already in `composer.json`)
- `picqer/php-barcode-generator` (already in `composer.json`)
- `barryvdh/laravel-dompdf` (already in `composer.json`, unconfigured — used for the printable PDF label sheet)
- `phpoffice/phpword` — **new dependency**, not yet in `composer.json`, needed for the printable Word (.docx) label sheet

## Service

`App\Services\TagService`

```php
generateBatch(int $quantity, User $actor): TagBatch  // code type comes from the global setting, not a param
assignToAsset(Tag $tag, Asset $asset, User $actor): AssetTagAssignment
requestReplacement(Asset $asset, Tag $newTag, string $reason, User $actor): TagReplacementRequest  // Phase 2
applyReplacement(TagReplacementRequest $request): void  // called by the ApprovalRequestApproved listener
resolveScan(string $tagNumber): ScanResult  // assigned | available | inactive
```

## Routes — Phase 1

| Method | URI | Action |
|--------|-----|--------|
| GET/POST | `/admin/tags/batches` | Generate batch (quantity only) |
| GET | `/admin/tags` | Tag pool list (filter by status) |
| GET | `/admin/tags/print/pdf` | Bulk print selected/batch tags as PDF |
| GET | `/admin/tags/print/word` | Bulk print selected/batch tags as Word (.docx) |
| GET/PATCH | `/admin/settings/tags` | View/update the global QR-vs-barcode setting |
| POST | `/assets/{asset}/tags/assign` | Assign available tag |
| DELETE | `/assets/{asset}/tags` | Unassign (only if no replacement policy — optional) |
| GET | `/scan/{tag_number}` | Scan resolver |
| POST | `/api/scan` | Log scan from mobile |

## Routes — Phase 2

| Method | URI | Action |
|--------|-----|--------|
| GET/POST | `/assets/{asset}/tags/replace` | Replacement request form |
| POST | `/tag-replacements/{id}/submit` | Submit for M08 approval |

## Tasks

### Phase 1
- [ ] `settings` table + `Setting` model (generic key-value, reusable beyond M05)
- [ ] Admin settings screen: global QR-vs-barcode toggle (`tag_code_type`), seeded default `qr`
- [ ] Batch generator UI (quantity only — no prefix/category input)
- [ ] Tag number generation: global sequential, non-repeating, derived from each tag row's own id — never category-based
- [ ] QR or barcode image per tag in pool, per whichever `code_type` the batch snapshotted
- [ ] Tag pool list: available / assigned / inactive filters
- [ ] Bulk print for selected/batch available tags — downloadable as both **PDF** and **Word (.docx)**
- [ ] Assign tag to asset (picker + scan-to-assign for available tags) — always after the asset already exists
- [ ] Asset create/edit: tag optional; no auto-generation on save
- [ ] Sync `assets.asset_tag` from active assignment
- [ ] Tag history on asset detail (all assignments)
- [ ] Scan resolver: assigned → asset detail; available → assign prompt; inactive → retired message + link to current tag
- [ ] Log all scans to `scan_logs`
- [ ] Permissions: `tags.generate`, `tags.assign`, `tags.view`, `tags.print`, `settings.manage`

### Phase 2 (with M08)
- [ ] Replacement request: reason + pick new available tag
- [ ] Submit → `WorkflowService::submit($request, 'tag_replacement')`
- [ ] On approve → `TagService::applyReplacement()` deactivates old, activates new
- [ ] Permission: `tags.replace`

## Acceptance criteria

- Tags generated in bulk without needing an asset
- Labels printable before any asset exists
- Asset can exist without tag; tag assignable later from pool
- Only one active tag per asset
- Replaced tags scan as inactive with clear messaging
- Replacement cannot complete without full approval chain
- Tag numbers never reused
- Tag numbering is global and sequential, independent of asset category — no category prefix, no category-based assignment restriction
- Printable label sheets download as both PDF and Word

## Handoff

- Scan URL format: `/scan/{tag_number}` — contract for M10 audit QR verification
- ~~M08 must add `tag_replacement` workflow module enum~~ — ✅ **already done**, see the M08 note below
- M03: remove any auto-tag-on-create logic; expose `activeTag` relationship
