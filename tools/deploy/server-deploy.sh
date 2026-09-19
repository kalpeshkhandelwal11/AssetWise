#!/bin/bash
#
# AssetWise deploy script — run on the cPanel server over SSH / Terminal.
# Prereqs: upload  assetwise-deploy.tar.gz  to your home dir (~) first.
#
# Run:  bash ~/server-deploy.sh   2>&1 | tee ~/deploy.log
#
set -uo pipefail

###### ---- CONFIG (edit only if your paths differ) ----
SUBDOMAIN="assetwise.mandeepa.com"
APP_DIR="$HOME/assetwise"                                 # private app root (NOT web-exposed)
DOCROOT="$HOME/public_html/${SUBDOMAIN}"                  # current subdomain document root
TARBALL="$HOME/assetwise-deploy.tar.gz"                   # uploaded build artifact
DB_SUFFIX="assetwise"                                     # final DB = <cpuser>_assetwise
DBUSER_SUFFIX="awuser"                                    # final user = <cpuser>_awuser
# SECRETS ARE NEVER STORED IN THE REPO. Provide DB_PASSWORD via the environment
# (e.g. `DB_PASSWORD=... bash server-deploy.sh`), otherwise a strong one is generated
# and printed once below. APP_KEY is generated on the server by `artisan key:generate`.
DB_PASSWORD="${DB_PASSWORD:-$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9')}"
###### -----------------------------------------------------

CPUSER=$(whoami)
DB_NAME="${CPUSER}_${DB_SUFFIX}"
DB_USER="${CPUSER}_${DBUSER_SUFFIX}"

# Pick a PHP 8.3 binary (cPanel/CloudLinux path first, then generic php)
if [ -x /opt/cpanel/ea-php83/root/usr/bin/php ]; then
  PHP=/opt/cpanel/ea-php83/root/usr/bin/php
else
  PHP=php
fi

echo "=================================================="
echo " cPanel user : $CPUSER"
echo " App dir     : $APP_DIR"
echo " Database    : $DB_NAME"
echo " DB user     : $DB_USER"
echo " PHP binary  : $PHP  ($($PHP -v | head -n1))"
echo "=================================================="

echo "== [1/7] Create database, user, grant (existing = harmless error) =="
uapi Mysql create_database name="$DB_NAME"
uapi Mysql create_user name="$DB_USER" password="$DB_PASSWORD"
uapi Mysql set_privileges_on_database user="$DB_USER" database="$DB_NAME" privileges='ALL PRIVILEGES'

echo "== [2/7] Extract application to $APP_DIR =="
mkdir -p "$APP_DIR"
tar -xzf "$TARBALL" -C "$APP_DIR"

cd "$APP_DIR" || { echo "FATAL: cannot cd to $APP_DIR"; exit 1; }

echo "== [3/7] Write .env =="
cat > .env <<ENV
APP_NAME=AssetWise
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://${SUBDOMAIN}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@${SUBDOMAIN}"
MAIL_FROM_NAME="AssetWise"

VITE_APP_NAME="AssetWise"
ENV

echo ">> DB password used (saved in .env — record it now): $DB_PASSWORD"

echo "== [3b] Generate APP_KEY into .env =="
$PHP artisan key:generate --force

echo "== [4/7] Permissions =="
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "== [5/7] Storage link + migrate + seed =="
$PHP artisan storage:link || true
$PHP artisan migrate --force --seed

echo "== [6/7] Optimize caches =="
# NOTE: do NOT run `config:cache` — config/notifications.php holds closures (M12
# catalog) that are not serializable and the command aborts. Clear it instead.
$PHP artisan config:clear
$PHP artisan route:cache
$PHP artisan view:cache

echo "== [7/7] Point subdomain document root to public/ =="
echo "Attempting via UAPI (may need cPanel UI fallback — see notes)..."
uapi SubDomain changedocroot domain="$SUBDOMAIN" docroot="assetwise/public" \
  || echo ">> UAPI changedocroot failed. Set Document Root to  $APP_DIR/public  in cPanel > Domains."

echo ""
echo "=================================================="
echo " DEPLOY COMPLETE"
echo " 1. Confirm docroot points at:  $APP_DIR/public"
echo " 2. Add cron:  * * * * * $PHP $APP_DIR/artisan schedule:run >/dev/null 2>&1"
echo " 3. Visit: https://${SUBDOMAIN}"
echo "=================================================="
