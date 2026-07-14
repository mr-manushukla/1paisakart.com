#!/usr/bin/env bash
# One-shot: create the MySQL database + a scoped app user, point .env at it, migrate & seed.
# You enter your OWN MySQL root password at the prompt — it is never stored.
# Usage:  bash backend/setup-mysql.sh
set -euo pipefail

DB=onepaisakart
APP_USER=paisa
APP_PASS=paisakart_local   # local dev only; change if you like
DIR="$(cd "$(dirname "$0")" && pwd)"

echo "→ Creating database '$DB' and user '$APP_USER' (enter your MySQL root password):"
mysql -uroot -p <<SQL
CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$APP_USER'@'localhost' IDENTIFIED BY '$APP_PASS';
GRANT ALL PRIVILEGES ON $DB.* TO '$APP_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "→ Pointing backend/.env at MySQL"
cd "$DIR"
grep -v '^DB_' .env > .env.tmp
cat >> .env.tmp <<ENV
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB
DB_USERNAME=$APP_USER
DB_PASSWORD=$APP_PASS
ENV
mv .env.tmp .env

echo "→ Migrating & seeding"
php artisan config:clear >/dev/null
php artisan migrate:fresh --seed

echo "✅ MySQL is live. Restart the server: php artisan serve"
