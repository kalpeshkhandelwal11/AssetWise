# Running AssetWise locally

Practical command reference for setting up, running, and operating the app on a dev machine
(Laragon on Windows in this repo). For the module/architecture overview see `CLAUDE.md`.

## Prerequisites

- **PHP 8.3+** (hard requirement — Laravel 13), with `mbstring, openssl, pdo_mysql, gd, intl, zip, exif, bcmath`
- **MySQL 8**
- **Node 20+** (this machine: Node 22) and **npm**
- **Composer**

On this machine the toolchain lives under Laragon and isn't on the global PATH. Prefix a shell
session with:

```powershell
# PHP
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;" + $env:Path
# Node/npm
$env:Path = "C:\laragon\bin\nodejs\node-v22;" + $env:Path
```

(Adjust the version folders if Laragon updates them.)

## First-time setup

```powershell
composer install
npm install                     # required — vite-plugin-pwa etc. must be present to build

# .env: copy the example if you don't have one, then set APP_KEY + DB_* + MAIL_*
copy .env.example .env          # only if .env is missing
php artisan key:generate

# Database (creates schema `assetwise` first): CREATE DATABASE assetwise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate:fresh --seed
php artisan storage:link        # so uploaded photos/attachments are web-accessible
npm run build                   # compiles JS/CSS + generates the PWA service worker into public/build
```

Seeded admin login: **admin@assetwise.test** / **Admin@1234** (forced password change on first login).

## Everyday run

```powershell
php artisan serve --host=127.0.0.1 --port=8000    # -> http://127.0.0.1:8000
# (or just use Laragon's vhost at http://assetwise.test)

npm run dev        # optional: Vite dev server with hot reload while editing assets
npm run build      # rebuild static assets (needed after Blade/Tailwind class changes if not running `npm run dev`)
```

**`npm run build` is required for the PWA to exist** — `/sw.js` 404s without `public/build`, and new
Tailwind classes added in Blade aren't styled until a rebuild (or while `npm run dev` runs).

## Reset the database

```powershell
php artisan migrate:fresh --seed      # drops everything, re-migrates, re-seeds demo data
```

Do this after pulling changes that add/alter columns or rename seeded data (e.g. status renames,
the DRAFT status, the asset-naming settings) — several of those live in migrations/seeders that only
take effect on a fresh migrate.

## Queue worker (needed for email)

`QUEUE_CONNECTION=database`. In-app (bell) notifications are inline and appear without a worker, but
**mail notifications are queued** and only go out when a worker is running:

```powershell
php artisan queue:work                 # long-running; processes queued jobs incl. mail + heavy exports
php artisan queue:work --stop-when-empty   # one-shot: drain the queue and exit
```

> ⚠️ **Email is currently not actually delivered.** `.env` has `MAIL_MAILER=log`, so mail is written
> to `storage/logs/laravel.log` instead of being sent. To send real email: set `MAIL_MAILER=smtp`
> with valid `MAIL_HOST/PORT/USERNAME/PASSWORD` (e.g. Mailpit locally, or a real SMTP provider) **and**
> keep a `queue:work` process running. Without the worker the mail job just sits in the `jobs` table
> forever while the in-app notification still shows. See the M12 note in `CLAUDE.md`.

## Scheduler

The schedule is defined in `bootstrap/app.php` (`withSchedule`). It is **not** a background service —
something must invoke `php artisan schedule:run` every minute; that command runs whatever is due.

Registered jobs (all daily, idempotent):

| Command | What it does |
|---|---|
| `approvals:escalate` | Widens approver eligibility for overdue approval steps |
| `alerts:expiry` | Fires warranty/AMC expiry notifications at the 30 / 7 / 1-day thresholds |
| `depreciation:post-monthly` | Posts depreciation lines for completed months |

Run it, per environment:

```powershell
# Local (Windows) — easiest: a foreground ticker that calls schedule:run every minute
php artisan schedule:work

# See what's registered / when it runs
php artisan schedule:list

# Run a due sweep once by hand (safe — all three are idempotent)
php artisan schedule:run
php artisan approvals:escalate        # or invoke a single command directly
```

**Production (shared hosting):** a single cron entry drives everything —
`* * * * * php /path/to/artisan schedule:run`. Note that scheduled jobs which send mail still depend
on a running `queue:work` process and a real `MAIL_MAILER`.

## Tests

```powershell
php artisan test                              # full suite (in-memory SQLite; never touches the dev DB)
php artisan test tests/Feature/Assets         # a directory
php artisan test --filter=CreateFormTest      # a class
```

## Known pending / follow-ups

- **Email delivery** — `MAIL_MAILER=log` + no worker running (see Queue worker above).
- **Creation approval** — the `asset_creation` workflow is not seeded by design; configure one at
  `/admin/workflows` for new assets to route through Draft → approval. With none active, assets save live.
- **Scheduler** — no cron/`schedule:work` runs automatically in dev; start it manually if you want the
  daily jobs to fire.
