# M16 — Depreciation

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Depends on** | M03 (assets with purchase_cost) |
| **Parallel with** | M09, M10, M11, M17 |

## Scope

Asset depreciation with **category defaults + per-asset override**. **Strategy pattern** supports multiple methods; **MVP activates straight-line only**.

## DB Tables

- `depreciation_methods` (code, calculator_class, is_active)
- `category_depreciation_defaults`
- `asset_depreciation_settings`
- `depreciation_schedule_lines`

## Architecture

```
DepreciationCalculatorInterface
├── StraightLineCalculator          ← MVP (active)
├── DecliningBalanceCalculator      ← stub, inactive
├── DoubleDecliningBalanceCalculator
├── SumOfYearsDigitsCalculator
└── UnitsOfProductionCalculator
```

`DepreciationService` resolves settings: asset override → category default → skip if none.

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/admin/depreciation-methods` | Method master (toggle active) |
| GET/PUT | `/admin/categories/{id}/depreciation` | Category defaults |
| GET/PUT | `/assets/{asset}/depreciation` | Per-asset override |
| GET | `/assets/{asset}/depreciation/schedule` | Schedule lines |
| POST | `/admin/depreciation/post-period` | Manual period post (admin) |

## Tasks

- [ ] Seed methods; only `straight_line` is_active=true
- [ ] Category depreciation defaults UI on category admin
- [ ] Asset financial tab: override fields + computed book value
- [ ] `StraightLineCalculator`: `(cost - salvage) / useful_life_months`
- [ ] Generate full schedule on asset save / settings change
- [ ] Artisan command `depreciation:post-monthly` in scheduler
- [ ] Permissions: `depreciation.manage`, `depreciation.view`
- [ ] Future: activate additional calculators without migration changes

## Acceptance criteria

- Category without defaults → no depreciation until configured
- Asset with override uses override values
- Schedule lines sum to depreciable amount over useful life
- Book value on asset matches latest posted line
- Inactive methods visible in admin but not selectable

## Handoff to M14

Expose `DepreciationReportQuery` for schedule export by period/category/department.

## User flow

See parent plan UF-19.
