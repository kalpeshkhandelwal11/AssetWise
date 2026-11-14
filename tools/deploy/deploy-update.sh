#!/bin/bash
#
# AssetWise IN-PLACE UPDATE — run on the server after uploading a fresh
# assetwise-deploy.tar.gz to ~ . Unlike server-deploy.sh this does NOT create the
# DB, does NOT rewrite .env, and does NOT seed. It updates code + runs migrations.
#
# Run:  bash ~/deploy-update.sh 2>&1 | tee ~/deploy-update.log
#
set -uo pipefail

APP_DIR="$HOME/assetwise"
TARBALL="$HOME/assetwise-deploy.tar.gz"

if [ -x /opt/cpanel/ea-php83/root/usr/bin/php ]; then
  PHP=/opt/cpanel/ea-php83/root/usr/bin/php
else
  PHP=php
fi

[ -f "$TARBALL" ]      || { echo "FATAL: $TARBALL not found (upload it first)"; exit 1; }
[ -d "$APP_DIR" ]      || { echo "FATAL: $APP_DIR missing — use server-deploy.sh for a fresh install"; exit 1; }
[ -f "$APP_DIR/.env" ] || { echo "FATAL: $APP_DIR/.env missing — refusing to update without it"; exit 1; }

cd "$APP_DIR" || exit 1
echo "== Using PHP: $($PHP -v | head -n1)"

echo "== [1/7] Back up .env =="
cp -a .env ".env.bak.$(date +%Y%m%d%H%M%S)"

echo "== [1b] Back up the database (migrations below change live data) =="
envval() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' | tr -d '\r'; }
DB_NAME=$(envval DB_DATABASE); DB_USER=$(envval DB_USERNAME); DB_PASS=$(envval DB_PASSWORD); DB_HOST=$(envval DB_HOST)
DB_BACKUP="$HOME/assetwise-db-$(date +%Y%m%d%H%M%S).sql.gz"
if MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --no-tablespaces -h "${DB_HOST:-localhost}" -u "$DB_USER" "$DB_NAME" | gzip > "$DB_BACKUP" \
   && [ -s "$DB_BACKUP" ]; then
  echo "   DB backup: $DB_BACKUP ($(du -h "$DB_BACKUP" | cut -f1))"
else
  rm -f "$DB_BACKUP"
  echo "FATAL: database backup failed — nothing has been changed; fix this before updating"
  exit 1
fi

echo "== [2/7] Maintenance mode ON =="
$PHP artisan down --render=errors::503 || $PHP artisan down || true

echo "== [3/7] Extract new build over app (tarball excludes .env, so it is preserved) =="
# --keep-directory-symlink stops tar from clobbering the public/storage symlink.
tar -xzf "$TARBALL" -C "$APP_DIR" --keep-directory-symlink

echo "== [4/7] Ensure writable dirs + storage link =="
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache
[ -L public/storage ] || $PHP artisan storage:link || true

echo "== [5/7] Migrate (no seed) =="
$PHP artisan migrate --force
$PHP artisan assetwise:sync-reference-data
$PHP artisan assets:backfill-coverage   # idempotent: onboarding expiry dates -> warranty/AMC records

echo "== [6/7] Refresh caches (NEVER config:cache — closures in config/notifications.php) =="
$PHP artisan config:clear
$PHP artisan route:cache
$PHP artisan view:cache

echo "== [7/7] Restart queue workers + maintenance mode OFF =="
$PHP artisan queue:restart || true
$PHP artisan up

echo ""
echo "== UPDATE COMPLETE =="
echo "Visit https://assetwise.mandeepa.com and spot-check. Old .env backup kept as .env.bak.*"
