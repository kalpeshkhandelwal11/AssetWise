# AssetWise — Developer Setup Guide

This guide takes you from a bare Windows machine to a fully running local development environment.

> **PHP 8.3 or newer is mandatory.** Laravel 13 declares `"php": "^8.3"`, and Composer's generated `vendor/composer/platform_check.php` throws before any application code runs on 8.2 — every `php artisan` command fails with *"Composer detected issues in your platform"*. If you have several PHP installs, confirm the one on your PATH is the 8.3+ build (`php -v`) before assuming the application is broken.

---

## Table of Contents

1. [Install Laragon](#1-install-laragon)
2. [Verify PHP extensions](#2-verify-php-extensions)
3. [Install Composer](#3-install-composer)
4. [Install Node.js](#4-install-nodejs)
5. [Install Git](#5-install-git)
6. [Create the database](#6-create-the-database)
7. [Clone the repository](#7-clone-the-repository)
8. [Install PHP dependencies](#8-install-php-dependencies)
9. [Install Node dependencies](#9-install-node-dependencies)
10. [Configure the environment](#10-configure-the-environment)
11. [Run migrations and seed](#11-run-migrations-and-seed)
12. [Link storage](#12-link-storage)
13. [Build frontend assets](#13-build-frontend-assets)
14. [Access the application](#14-access-the-application)
15. [Running the test suite](#15-running-the-test-suite)
16. [Queue worker](#16-queue-worker)
17. [Scheduler](#17-scheduler)
18. [Troubleshooting](#troubleshooting)
19. [Module implementation order](#module-implementation-order)

---

## 1. Install Laragon

Laragon is a portable, isolated local development environment for Windows. It bundles PHP, MySQL, Apache/Nginx, Composer, and Node.js into a single installer.

### Steps

1. Download **Laragon Full** from [https://laragon.org/download/](https://laragon.org/download/)
   - Choose **Laragon Full** (not Lite) — it includes MySQL, Composer, and Node.js.
   - Typical filename: `laragon-wamp.exe`

2. Run the installer. Accept defaults:
   - Install path: `C:\laragon` (recommended — shorter path avoids some Windows MAX_PATH issues)
   - Check **"Auto start services on Windows startup"** if you want MySQL/Apache to start automatically.

3. After installation, Laragon opens. Click **Start All** to launch Apache and MySQL.

4. Verify the services are green in the Laragon UI:
   - Apache: green
   - MySQL: green

### Virtual host auto-configuration

Laragon watches `C:\laragon\www\` and automatically creates a virtual host for each subfolder.  
If the project folder is `C:\laragon\www\AssetWise`, the app will be available at `http://assetwise.test` — no extra `/public` needed.

> **Windows hosts file** — Laragon patches `C:\Windows\System32\drivers\etc\hosts` automatically when it creates virtual hosts. You may need to run Laragon as Administrator the first time.

---

## 2. Verify PHP extensions

AssetWise requires these PHP extensions. All are enabled by default in Laragon Full.

| Extension | Purpose |
|-----------|---------|
| `mbstring` | Multi-byte string handling |
| `openssl` | Encryption, HTTPS |
| `pdo_mysql` | MySQL database driver |
| `gd` | Image processing (QR/barcodes) |
| `intl` | Internationalisation |
| `zip` | ZIP archives (export) |
| `exif` | Image metadata |
| `bcmath` | Arbitrary-precision maths (depreciation) |
| `fileinfo` | MIME-type detection |

### Verify

Open Laragon terminal (`Laragon → Terminal`) and run:

```bash
php -m | grep -E "mbstring|openssl|pdo_mysql|gd|intl|zip|exif|bcmath|fileinfo"
```

All eight names should appear in the output. If one is missing:

1. Open `C:\laragon\bin\php\php-8.x.x\php.ini`
2. Find the line `;extension=name` and remove the leading `;`
3. Restart Apache in Laragon

---

## 3. Install Composer

Laragon Full includes Composer. Verify it is on the PATH:

```powershell
composer --version
# Expected: Composer version 2.x.x
```

If `composer` is not found, open Laragon → Menu → Tools → Quick Add → Composer, or download manually from [https://getcomposer.org/download/](https://getcomposer.org/download/).

---

## 4. Install Node.js

Laragon Full bundles Node.js 20 LTS.

```powershell
node --version   # Expected: v20.x.x
npm --version    # Expected: 10.x.x
```

If Node is missing, download from [https://nodejs.org/](https://nodejs.org/) — choose **LTS** — and install it. Laragon will pick it up automatically on next restart.

---

## 5. Install Git

Git is not bundled with Laragon.

1. Download from [https://git-scm.com/download/win](https://git-scm.com/download/win)
2. Run the installer. Accept defaults. Ensure **"Git Bash"** and **"Git from the command line"** are selected.
3. Verify:

```powershell
git --version
# Expected: git version 2.x.x
```

---

## 6. Create the database

### Using Laragon's built-in HeidiSQL

1. In the Laragon taskbar tray icon, right-click → **Database** (or click the Database button in the UI).
2. HeidiSQL opens and connects automatically to `localhost` root (no password by default).
3. Right-click **Unnamed → Create new → Database**.
4. Name: `assetwise`, Collation: `utf8mb4_unicode_ci` → OK.

### Using MySQL CLI

Open Laragon Terminal and run:

```sql
mysql -u root -e "CREATE DATABASE assetwise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## 7. Clone the repository

Open Laragon Terminal (or PowerShell) and clone into Laragon's web root:

```powershell
cd C:\laragon\www
git clone <repo-url> AssetWise
cd AssetWise
```

> The folder must be named `AssetWise` (capital A and W) for the virtual host `assetwise.test` to resolve correctly.

---

## 8. Install PHP dependencies

```powershell
composer install
```

This installs all packages listed in `composer.json`, including:
- Laravel Framework 13 (requires PHP 8.3+)
- Spatie Laravel Permission
- Spatie Activity Log
- Maatwebsite Laravel Excel
- DomPDF
- simplesoftwareio/simple-qrcode
- picqer/php-barcode-generator

---

## 9. Install Node dependencies

```powershell
npm install
```

This installs Vite, Tailwind CSS, Alpine.js, and the PWA plugin.

---

## 10. Configure the environment

### Create .env from example

```powershell
copy .env.example .env
php artisan key:generate
```

### Edit .env

Open `.env` and verify these values match your local setup:

```ini
APP_NAME=AssetWise
APP_ENV=local
APP_DEBUG=true
APP_URL=http://assetwise.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=assetwise
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

> **Laragon default** — root password is empty. If you set a MySQL password during Laragon setup, enter it in `DB_PASSWORD`.

---

## 11. Run migrations and seed

```powershell
php artisan migrate --seed
```

This creates all tables and seeds:

| Seeded data | Detail |
|-------------|--------|
| Roles | Super Admin, Asset Manager, Department User, Auditor, Approver, Viewer |
| Permissions | All `{module}.{action}` permissions (masters, assets, movement, etc.) |
| Demo admin | `admin@assetwise.test` / `Admin@1234` with Super Admin role |
| Shared masters | Statuses, asset types, priorities, movement/audit/disposal/maintenance types, sample departments and branches |
| Approval workflows | Two defaults — *Standard Transfer Approval* (Approver → Asset Manager) and *Standard Tag Replacement Approval* (Asset Manager → Super Admin), both 48h escalation. `disposal` and `kit_assignment` are intentionally left unconfigured for M13/M17 |

To reset and re-seed from scratch at any time:

```powershell
php artisan migrate:fresh --seed
```

---

## 12. Link storage

```powershell
php artisan storage:link
```

Creates `public/storage` → `storage/app/public` symlink for user-uploaded files.

---

## 13. Build frontend assets

For **development** (hot-reload, watch mode):

```powershell
npm run dev
```

Leave this running in a terminal while developing — Vite recompiles on file change.

For **production build**:

```powershell
npm run build
```

---

## 14. Access the application

Open your browser:

```
http://assetwise.test
```

Log in with the seeded admin account:

| Field | Value |
|-------|-------|
| Email | `admin@assetwise.test` |
| Password | `Admin@1234` |

> If the virtual host is not resolving, try `http://localhost/AssetWise/public` as a fallback, or restart Laragon as Administrator.

---

## 15. Running the test suite

All tests use an **in-memory SQLite** database — they never touch your development database and run without any services running.

```powershell
# Run all tests
php artisan test

# Run a single test class
php artisan test --filter=LoginHistoryListTest

# Run a directory
php artisan test tests/Feature/Auth/

# Run with coverage (requires Xdebug or PCOV)
php artisan test --coverage
```

Expected output: **578 tests, 0 failures**.

### Test conventions

| Rule | Detail |
|------|--------|
| Factory password | `Admin@1234` — satisfies the global strong-password policy (min 8, mixed case, numbers, symbols) |
| New password in update flows | `NewPass@9876` |
| Session-expiry tests | login → one authenticated GET → `$this->travel()` → assert redirect |
| Role/permission tests | `use Tests\Concerns\SeedsRolesAndPermissions` + `$this->createUserWithRole('Role Name')` |
| Event listeners | Auto-discovered — never register manually in `AppServiceProvider` |
| Event assertions | Use partial `Event::fake([SomeEvent::class])`; a bare `Event::fake()` also swallows the Eloquent model events `LogsActivity` needs |
| Approval/escalation tests | `$this->travel(49)->hours()` → act → `$this->travelBack()`; `Asset` stands in as the `approvable` |

---

## 16. Queue worker

Heavy operations (bulk exports, PDF generation, and — since M12 — notification emails) run as
queued jobs. Start the worker with:

```powershell
php artisan queue:work --stop-when-empty
```

For development convenience, set `QUEUE_CONNECTION=sync` in `.env` to run jobs inline without a worker.

### Notification email failure mode (M12) — read this before it confuses you

In-app (bell/dropdown) notifications write inline and always appear immediately, worker or
not. **Email does not.** `NotificationService::send()` dispatches a *separate*,
`ShouldQueue` notification (`App\Notifications\GenericMailNotification`) for the mail
channel — with `QUEUE_CONNECTION=database` and no `queue:work` running, that job sits in the
`jobs` table forever and **no error appears anywhere**. If "the bell updated but no email
arrived," check `queue:work` is running before anything else.

### Mailpit (local email testing)

Laragon Full ships Mailpit. Point mail at it in `.env`:

```ini
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

Sent mail (including M12's `GenericMailNotification`) appears at `http://localhost:8025` —
nothing leaves your machine. If Mailpit isn't running, Laragon's tray icon has a start
toggle for it alongside MySQL/Nginx.

---

## 17. Scheduler

Scheduled work is registered in `bootstrap/app.php` under `->withSchedule()`. Currently registered:

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `approvals:escalate` | daily | Escalates pending approval steps past their `escalation_hours` (M08). Idempotent — re-running never double-escalates |

Depreciation posting (`depreciation:post-monthly`, M16) and expiry alerts (`alerts:expiry`, M11) also run daily from this block. M12 shipped without a digest job — see its decisions-log entry (D12.3, "wire channels only").

**Local development** — run manually:

```powershell
php artisan schedule:list   # confirm what is registered
php artisan schedule:run    # run anything currently due
php artisan approvals:escalate   # or invoke a single command directly
```

**Production** — add one cron entry on the server:

```
* * * * * php /path/to/assetwise/artisan schedule:run >> /dev/null 2>&1
```

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL not running | Click **Start All** in Laragon |
| `Class not found` after `composer install` | Autoloader stale | `composer dump-autoload` |
| Assets returning 404 | Vite not built | Run `npm run dev` or `npm run build` |
| `Permission denied` on storage | Symlink missing | `php artisan storage:link` |
| Tests fail with `no such table` | Missing migration | `php artisan migrate` (tests use `:memory:` SQLite) |
| Login always redirects to `/password/change` | Seeded user has `must_change_password = true` | Log in and set a new password, or `php artisan migrate:fresh --seed` |
| `http://assetwise.test` not resolving | Virtual host not created | Restart Laragon as Administrator; check `hosts` file |
| `npm run dev` port 5173 already in use | Another Vite process running | Kill with `npx kill-port 5173` |
| `php artisan` fails with `APP_KEY` missing | `.env` not set up | Run `php artisan key:generate` |
| `Composer detected issues in your platform: ... requires ">= 8.3.0"` | PATH `php` is 8.2 or older | Install/select PHP 8.3+; if several are installed, invoke the 8.3 binary explicitly (e.g. `C:\php83\php.exe artisan test`) |
| Every page renders unstyled after `npm run build` | Stale `public/hot` left by a killed `npm run dev` | Delete `public/hot` — Vite only falls back to the built manifest when that file is absent |

---

## Module implementation order

```
M00 ✓  Foundation (scaffold, packages, base UI)
M01 ✓  Auth & RBAC (session lifetime, force-change, login history)
M02 ✓  Shared Masters (companies, statuses, locations)
M03 ✓  Asset Master (categories, assets, photos, attachments)
M04 ✓  Dynamic Fields (per-category EAV with inheritance/override)
M08 ✓  Approval Workflow (multi-level, escalation, approver inbox)
M05 →  QR/Barcode Tags        — NEXT (unblocked; replacement flow can now use M08)
M06 →  Bulk Import/Export     — NEXT (unblocked; M03 + M04 both done)
M07    Shared UI Services
M09    Asset Movement         (needs M03 + M08 — both now done)
M10    Audit                  (needs M03 + M05)
M11    Maintenance
M12    Notifications          (M08 shipped a database-channel stub)
M13    Disposal               (needs M03 + M08 — both now done)
M14    Reports & Dashboard
M15    PWA
M16    Depreciation
M17    Asset Kits
```

M08 landing out of numeric order is by design — it is the Dev 2 track and gates M09/M13/M17.

See `docs/planning/MODULES_INDEX.md` for the full dependency graph and parallel-track breakdown.
