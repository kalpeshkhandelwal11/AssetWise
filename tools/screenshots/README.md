# User-handbook screenshot generator

Regenerates the images in [`../../docs/user-guide/screenshots/`](../../docs/user-guide/screenshots/) that the [AssetWise User Handbook](../../docs/user-guide/AssetWise-User-Handbook.md) embeds.

It drives the **real app** with [Playwright](https://playwright.dev/), so the screenshots always match the current UI. Run it after UI changes to refresh the guide.

## How it works

`generate.ps1` does everything, non-destructively:

1. Creates a throwaway **SQLite** database (`database/screenshots.sqlite`) — your MySQL dev data is never touched.
2. Migrates it and seeds the core masters/roles, then loads demo data (`seed-demo.php`): a category with custom fields, six sample assets, and one live depreciation schedule.
3. Serves the app locally with `php artisan serve` (with an `APP_URL` override so styling loads).
4. Runs **`capture.cjs`** — logs in and captures all 38 documented screens (full page).
5. Runs **`annotate.cjs`** — re-captures ~8 walked-through screens with call-out boxes/labels on the specific fields and actions the handbook describes.
6. Stops the server and deletes the throwaway database.

## Prerequisites

- **PHP 8.3+**, **Node 20+**, and the app's PHP/JS deps installed (`composer install`, `npm run build` at the repo root so `public/build` exists).
- On Windows/Laragon the script auto-finds `php.exe`; otherwise put `php` on your PATH.

## Run

```powershell
cd tools/screenshots
npm install        # one time — installs Playwright here (browsers are cached globally)
./generate.ps1     # regenerate all screenshots
# ./generate.ps1 -Port 8231   # if 8123 is busy
```

Then review the diff in `docs/user-guide/screenshots/` and commit.

## Customising

- **Add / reorder screens** — edit the `pages` array in `capture.cjs` (`['file-name', '/route']`).
- **Change or add call-outs** — edit the `jobs` array in `annotate.cjs`. Each target is either `{ sel: '#css' }` or `{ text: 'Button label', tag: 'button' }`, plus a `label` (and optional `color`). `pre(page)` can set the form state first (e.g. select a category so its custom fields render).
- **Change demo data** — edit `seed-demo.php`.

The login user is the seeded admin (`admin@assetwise.test` / `Admin@1234`); `seed-demo.php` clears its force-password-change flag so the run lands on the dashboard.

## Notes

- `node_modules/` and any `*.sqlite` here are git-ignored.
- `SingareenipallyAssetSeeder` is intentionally skipped — it uses MySQL-only `SET FOREIGN_KEY_CHECKS` and won't run on SQLite; `seed-demo.php` provides demo assets instead.
