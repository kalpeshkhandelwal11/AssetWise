# AssetWise — Shared Hosting Deployment Guide

This guide covers deploying AssetWise on a typical shared hosting provider (cPanel, Plesk, or DirectAdmin) where you do not have root access or Docker.

> **Minimum server requirements**
> - **PHP 8.3+** (hard requirement — Laravel 13; the app will not boot on 8.2) with extensions: `mbstring`, `openssl`, `pdo_mysql`, `gd`, `intl`, `zip`, `exif`, `bcmath`, `fileinfo`
> - MySQL 8.0+
> - SSH access (strongly recommended; FTP-only hosting is very limiting)
> - Ability to set a custom document root to `/public` OR place files outside public_html (see below)

---

## Table of Contents

1. [Prepare files locally](#1-prepare-files-locally)
2. [Upload to server](#2-upload-to-server)
3. [Point the document root to /public](#3-point-the-document-root-to-public)
4. [Create the database](#4-create-the-database)
5. [Configure .env](#5-configure-env)
6. [Install dependencies on the server](#6-install-dependencies-on-the-server)
7. [Run migrations and seed](#7-run-migrations-and-seed)
8. [Link storage](#8-link-storage)
9. [Set file permissions](#9-set-file-permissions)
10. [Configure cron for the scheduler](#10-configure-cron-for-the-scheduler)
11. [Enable HTTPS / SSL](#11-enable-https--ssl)
12. [Verify the deployment](#12-verify-the-deployment)
13. [Updating the application](#updating-the-application)
14. [Troubleshooting](#troubleshooting)

---

## 1. Prepare files locally

Build production assets before uploading. Run on your local machine:

```powershell
composer install --no-dev --optimize-autoloader
npm run build
```

`--no-dev` excludes dev-only packages (PHPUnit, Faker, etc.) to reduce upload size.

---

## 2. Upload to server

### Option A — SSH + Git (recommended)

If your host provides SSH access and Git:

```bash
ssh user@yourserver.com
cd ~/                          # home directory
git clone <repo-url> assetwise
```

Then proceed to step 3.

### Option B — FTP / File Manager

1. Compress the project (excluding `.git/`, `node_modules/`) into a `.zip`.
2. Upload via cPanel File Manager or an FTP client (FileZilla).
3. Extract on the server into your home directory (e.g., `/home/username/assetwise`), **not** inside `public_html`.

> **Why outside public_html?** The Laravel app root contains sensitive files (`.env`, `vendor/`). Only the `public/` subfolder should be web-accessible.

---

## 3. Point the document root to /public

The web server must serve files from `assetwise/public/`, not from `assetwise/`.

### cPanel — Subdomains or Addon Domains

1. **cPanel → Subdomains** (or **Addon Domains**).
2. Create the domain/subdomain (e.g., `assets.yourdomain.com`).
3. Set **Document Root** to `/home/username/assetwise/public`.

### cPanel — Primary domain

If deploying to the primary domain and you cannot change the document root:

1. Move all files inside `assetwise/public/` into `public_html/`.
2. Edit `public_html/index.php` — change the two `__DIR__` paths to point to the parent folder:

```php
// Before
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// After (adjust path to match your layout)
require __DIR__.'/../../assetwise/vendor/autoload.php';
$app = require_once __DIR__.'/../../assetwise/bootstrap/app.php';
```

3. Copy `assetwise/public/.htaccess` into `public_html/`.

> This option is fragile. Using a subdomain with a custom document root (Option A above) is strongly preferred.

### Plesk

1. **Plesk → Websites & Domains → your domain → Document Root**.
2. Change to `assetwise/public`.
3. Click **OK** and restart Apache/Nginx.

### .htaccess (Apache)

The `public/.htaccess` file is already included in the project. It rewrites all requests through `index.php`. No changes needed.

---

## 4. Create the database

### cPanel → MySQL Databases

1. **cPanel → MySQL Databases**.
2. Create a new database: `username_assetwise`.
3. Create a new database user: `username_awuser` with a strong password.
4. Add the user to the database and grant **All Privileges**.
5. Note the database name, username, and password for the next step.

---

## 5. Configure .env

SSH into the server and create `.env` from the example:

```bash
cd ~/assetwise
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```ini
APP_NAME=AssetWise
APP_ENV=production
APP_DEBUG=false
APP_URL=https://assets.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=username_assetwise
DB_USERNAME=username_awuser
DB_PASSWORD=your_strong_password

CACHE_STORE=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=480

MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=AssetWise
```

> **`APP_DEBUG=false`** — always in production. Debug mode exposes stack traces and environment variables to the browser.

> **Cache/Session as `file`** — shared hosting rarely has Redis or Memcached. File driver works reliably.

---

## 6. Install dependencies on the server

If you used Git to clone, run Composer on the server:

```bash
cd ~/assetwise
composer install --no-dev --optimize-autoloader
```

If you uploaded via FTP with `vendor/` included, skip this step.

---

## 7. Run migrations and seed

```bash
php artisan migrate --force
php artisan db:seed --force
```

`--force` is required in production environment (Laravel refuses to run destructive commands without it).

This seeds:
- Roles and permissions
- Demo admin: `admin@assetwise.test` / `Admin@1234`

> **Change the admin password immediately after first login.**
>
> ⚠️ **`db:seed` is install-only, not a routine deploy step.** `RolePermissionSeeder` calls `syncPermissions()`, which is destructive — once a Super Admin starts editing roles/permissions through `/admin/roles`, re-running `db:seed` silently reverts every one of those edits back to the seeded defaults. Do **not** include `php artisan db:seed` in the "Updating the application" steps below after the first install.

---

## 8. Link storage

```bash
php artisan storage:link
```

Creates `public/storage` → `storage/app/public` for user-uploaded files.

> If the server doesn't support symlinks (rare), use cPanel → File Manager to create the symlink manually, or use the `FILESYSTEM_DISK=public` strategy and serve uploads via a controller.

---

## 9. Set file permissions

Laravel needs write access to `storage/` and `bootstrap/cache/`:

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache   # adjust user to your hosting's web user
```

On most cPanel hosts the web user is your cPanel username:

```bash
chown -R username:username storage bootstrap/cache
```

---

## 10. Configure cron for the scheduler

Laravel's task scheduler requires one cron entry. Everything scheduled is registered in `bootstrap/app.php` under `->withSchedule()`, so this single entry covers all of it — no cron changes are needed when a module adds a job.

Currently scheduled: **`approvals:escalate`** (daily) — escalates approval steps past their configured `escalation_hours`. It is idempotent, so a missed or double run is harmless. Depreciation posting (M16) and notification digests (M12) will join the same block.

Verify what is registered with `php artisan schedule:list` over SSH.

### cPanel → Cron Jobs

1. **cPanel → Cron Jobs**.
2. Set frequency to **Every minute** (all five `*` fields).
3. Command:

```
/usr/local/bin/php /home/username/assetwise/artisan schedule:run >> /dev/null 2>&1
```

> Adjust the PHP binary path. On most cPanel hosts it is `/usr/local/bin/php`. You can find it with `which php` over SSH.

---

## 11. Enable HTTPS / SSL

HTTPS is **required** for the PWA service worker and for `html5-qrcode` camera access.

### cPanel — Free Let's Encrypt SSL

1. **cPanel → SSL/TLS → Let's Encrypt SSL**.
2. Select your domain → Issue Certificate.
3. Auto-renews every 90 days.

### Force HTTPS in .env

```ini
APP_URL=https://assets.yourdomain.com
```

### Force HTTPS via .htaccess

Add to `public/.htaccess` (before the Laravel rewrite rules):

```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 12. Verify the deployment

Run through this checklist after deploying:

- [ ] `https://assets.yourdomain.com` loads the login page (no HTTP redirect loop)
- [ ] Login with `admin@assetwise.test` / `Admin@1234` succeeds
- [ ] Change the admin password immediately (first-login force-change prompt appears)
- [ ] Dashboard loads without JS or CSS 404 errors (check browser Network tab)
- [ ] File upload works (try attaching a file on any form)
- [ ] Queue table exists: `php artisan queue:work --stop-when-empty` exits cleanly
- [ ] Cron is running: check `storage/logs/laravel.log` after one minute for scheduler output

---

## Updating the application

```bash
cd ~/assetwise

# Pull latest code
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Build new assets (or pre-build locally and upload public/build/)
npm ci && npm run build

# Run new migrations
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Re-cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| Blank white page | `APP_DEBUG=false` hiding errors | Temporarily set `APP_DEBUG=true`, check `storage/logs/laravel.log` |
| 500 Internal Server Error | Wrong file permissions | `chmod -R 775 storage bootstrap/cache` |
| Database connection refused | Wrong credentials in `.env` | Double-check host, db name, user, password |
| CSS/JS returning 404 | `public/build/` not uploaded or `npm run build` not run | Run `npm run build` locally and upload `public/build/` |
| Images/uploads not loading | `storage:link` failed | Create symlink manually in File Manager |
| Login redirect loop | `APP_URL` uses HTTP but server forces HTTPS | Set `APP_URL=https://...` in `.env` |
| Scheduler not running | Wrong PHP path in cron | Verify with `which php` over SSH |
| PWA / camera not working | Site not on HTTPS | Install SSL certificate; camera access requires secure context |
| `php artisan migrate` fails with "Access denied" | DB user lacks privileges | Re-grant privileges in cPanel MySQL Databases |
| `No application encryption key` | `.env` missing `APP_KEY` | Run `php artisan key:generate` |
