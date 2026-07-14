#!/bin/bash
# Backend redeploy from git for 1paisakart (GoDaddy cPanel). Lives on the server
# at ~/deploy.sh. node/npm are NOT installed on the host, so the FRONTEND is built
# locally (npm run build) and its output uploaded; this script handles the backend.
set -euo pipefail

PHP=/opt/cpanel/ea-php84/root/usr/bin/php   # direct binary (the `php` wrapper needs a cache dir)
SRC="$HOME/deploy-src"                       # git clone of this repo
APP="$HOME/onepaisakart"                     # live Laravel app (outside web root)

echo "==> git pull"
cd "$SRC"
git pull --ff-only

echo "==> sync backend code (preserve .env, storage, vendor, bootstrap/cache, spa.html)"
rsync -a --delete \
  --exclude '.env' --exclude 'storage' --exclude 'vendor' \
  --exclude 'bootstrap/cache' --exclude 'public/spa.html' \
  --exclude '.git' --exclude 'tests' --exclude 'database/database.sqlite' \
  "$SRC/backend/" "$APP/"

echo "==> composer install (no-dev)"
cd "$APP"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> migrate"
$PHP artisan migrate --force

echo "==> recache"
$PHP artisan config:clear >/dev/null
$PHP artisan config:cache
$PHP artisan view:cache
$PHP artisan event:cache

echo "==> BACKEND DEPLOYED."
echo "    Frontend changed? Locally: cd frontend && npm run build, then upload"
echo "    dist/assets/* -> public_html/1paisakart.com/assets/  and  dist/index.html -> onepaisakart/public/spa.html"
