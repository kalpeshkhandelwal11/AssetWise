# M00 — Project Foundation

| | |
|--|--|
| **Developer** | Dev 1 (both developers consume) |
| **Phase** | 1 |
| **Depends on** | Laragon installed |
| **Blocks** | All modules |

## Scope

Scaffold Laravel 11 app in `G:\AssetWise`, install core packages, base layout, routing structure, `.env` for MySQL.

## Deliverables

- [ ] `composer create-project laravel/laravel .`
- [ ] Laravel Breeze (Blade stack)
- [ ] Spatie Permission + Activity Log
- [ ] Tailwind + Alpine configured (via Breeze)
- [ ] App layout: sidebar shell, flash messages, guest layout
- [ ] `config/assetwise.php` for app-specific settings (includes `kit_assignment_approval_mode`)
- [ ] Database connected to `assetwise` on Laragon MySQL
- [ ] Folder structure: `Controllers/Admin`, `Controllers/Assets`, `Services/`, `resources/views/modules/`
- [ ] Git init + `.gitignore` verified
- [ ] README with `php artisan serve` or Laragon vhost instructions

## Packages (composer)

```
laravel/breeze
spatie/laravel-permission
spatie/laravel-activitylog
```

## Routes stub

```
/dashboard          → placeholder
/admin/*            → M01+
/assets/*           → M03+
```

## Acceptance criteria

- App loads at local URL without errors
- Login/register works (Breeze default until M01 customizes)
- Migrations run clean on empty database
- Both developers can clone/open project and run `composer install && npm install && npm run build`

## Handoff

Notify Dev 2 when merged to `develop`. Dev 2 can branch for M08 after M01 permissions exist.
