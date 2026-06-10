# M04 — Dynamic Fields & Metadata

| | |
|--|--|
| **Developer** | Dev 1 |
| **Phase** | 1 |
| **Depends on** | M03 |
| **Blocks** | M06 |
| **Parallel with** | M05, M11 |

## Scope

Category-wise custom fields with inheritance + override, EAV storage, category lock, searchable attributes.

## DB Tables

- `category_fields`
- `category_field_options`
- `category_field_overrides`
- `asset_field_values` (EAV typed columns)

## Key service

`App\Services\DynamicFieldService`

```php
resolveForCategory(int $categoryId): Collection  // merged fields with overrides
validate(array $input, Collection $fields): array
saveValues(Asset $asset, array $input, Collection $fields): void
searchQuery(Builder $query, string $fieldKey, mixed $value): Builder
```

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/admin/categories/{cat}/fields` | CategoryFieldController |
| POST | `/admin/categories/{cat}/fields/{field}/override` | FieldOverrideController |
| GET | `/api/categories/{cat}/fields` | JSON for Alpine form load |

## Tasks

- [ ] Field builder UI on category admin
- [ ] Override UI on child categories (hide, relabel, change required)
- [ ] Soft-delete fields (read-only on existing assets)
- [ ] Integrate dynamic fields into asset create/edit (AJAX load on category change)
- [ ] Server validation from resolved schema
- [ ] EAV save/load
- [ ] **Category lock:** block `category_id` change when `asset_field_values` exist; allow with `assets.override_category`
- [ ] Mark searchable fields; add to asset list filters
- [ ] Show soft-deleted field values read-only on asset detail

## Acceptance criteria

- Parent fields appear on child category forms
- Child can hide inherited field
- Values persist and validate per field type
- Category immutable after custom data saved (except admin override)
- Searchable custom fields filter asset list

## Handoff

`resolveForCategory()` must be stable before M06 bulk import.
