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
