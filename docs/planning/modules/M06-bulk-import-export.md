# M06 — Bulk Import / Export

| | |
|--|--|
| **Developer** | Dev 1 |
| **Phase** | 1 |
| **Depends on** | M03, M04 |
| **Parallel with** | M08 |

## Scope

Per-category Excel template download, bulk upload with row validation, filtered export.

## DB Tables

- `import_batches` (category_id, user_id, filename, status, total_rows, success_count, error_count)
- `import_batch_rows` (batch_id, row_number, status, errors json, asset_id nullable)

## Packages

- `maatwebsite/excel`

## Routes

| Method | URI | Action |
|--------|-----|--------|
| GET | `/assets/import` | Import wizard |
| GET | `/assets/import/template/{category}` | Download category template |
| POST | `/assets/import` | Process upload |
| GET | `/assets/import/{batch}` | Batch status + error report |
| GET | `/assets/export` | Export filtered assets Excel |

## Tasks

- [ ] Template export: core columns + resolved custom columns for selected category
- [ ] Import parser with row-level validation via `DynamicFieldService`
- [ ] Error report download (failed rows + reasons)
- [ ] Successful rows create assets + field values + trigger QR generation
- [ ] Export respects list filters; includes custom fields per asset category
- [ ] Permission: `assets.bulk`, `assets.export`

## Acceptance criteria

- Template matches category field schema exactly
- Invalid rows reported; valid rows imported atomically per row
- No silent partial failures
- Export opens in Excel with readable headers
