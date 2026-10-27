#!/usr/bin/env bash
# Backend redeploy for 1paisakart on Hostinger shared hosting (CloudLinux 8).
# Lives on the server at ~/apps/1paisakart/deploy.sh.
#
# Layout, which differs from the old cPanel box:
#   ~/apps/1paisakart                       Laravel root — OUTSIDE the web root
#   ~/domains/1paisakart.com/public_html    only Laravel's public/ + the Vue build
# The account hosts five domains, so every path here is pinned to 1paisakart.
#
# node/npm are NOT installed, so the FRONTEND is built locally and uploaded:
#   cd frontend && npm run build
#   dist/assets/*   -> domains/1paisakart.com/public_html/assets/
#   dist/index.html -> apps/1paisakart/public/spa.html
set -euo pipefail

PHP=/opt/alt/php84/usr/bin/php     # CLI `php` is 7.4; dependencies need >= 8.4.1
COMPOSER=/usr/local/bin/composer
APP="$HOME/apps/1paisakart"
SRC="$HOME/deploy-src/1paisakart"
WEB="$HOME/domains/1paisakart.com/public_html"

case "$WEB" in
  "$HOME/domains/1paisakart.com/public_html") ;;
  *) echo "REFUSING: unexpected web root [$WEB]"; exit 1 ;;
esac

echo "==> git pull"
git -C "$SRC" fetch --all --quiet
git -C "$SRC" reset --hard origin/master --quiet
git -C "$SRC" log --oneline -1

echo "==> sync backend (preserve .env, storage, vendor, bootstrap/cache, spa.html)"
rsync -a --delete \
  --exclude '.env' --exclude 'storage' --exclude 'vendor' --exclude 'bootstrap/cache' \
  --exclude '.git' --exclude 'tests' --exclude 'database/database.sqlite' \
  --exclude 'public/spa.html' --exclude 'deploy.sh' --exclude 'set-db-creds.sh' \
  "$SRC/backend/" "$APP/"

echo "==> composer install (no-dev)"
cd "$APP"
"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3

echo "==> front controller (absolute paths — the web root is not the app root)"
sed "s#__DIR__\.'/\.\./#'$APP/#g" "$APP/public/index.php" > "$WEB/index.php"
grep -q "__DIR__" "$WEB/index.php" && { echo "index.php still has __DIR__ — aborting"; exit 1; } || true

echo "==> migrate"
"$PHP" artisan migrate --force

echo "==> recache"
"$PHP" artisan config:clear >/dev/null
"$PHP" artisan config:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache
# route:cache is skipped — some routes are closures.

echo "==> BACKEND DEPLOYED."
