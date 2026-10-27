# Deployment — Hostinger shared hosting (current)

Live: **https://1paisakart.com** — Hostinger shared (CloudLinux 8), SSH on port **65002**.
Migrated off GoDaddy cPanel on 2026-10-27; that older setup is kept at the bottom for reference.

## Layout on the server
```
~/apps/1paisakart/                      Laravel root — OUTSIDE the web root
~/deploy-src/1paisakart/                git clone, pulled by deploy.sh
~/domains/1paisakart.com/public_html/   web root — ONLY Laravel's public/ + the Vue build:
    index.php                           front controller, absolute paths into ~/apps/1paisakart
    .htaccess                           Laravel's public/.htaccess + the PHP 8.4 handler
    assets/                             Vue build output (static)
    uploads/                            product/slide images (UPLOADS_ROOT points here)
~/backups/1paisakart-<ts>/              taken automatically by the one-time restructure
```

**The app must stay outside the web root.** The initial upload put the whole Laravel
root *in* `public_html`, which served `.env`, `storage/logs/laravel.log` and the entire
source tree over HTTP. `public_html` now holds only what `public/` holds.

## Gotchas specific to this host
- **This account hosts five domains** (`maslindia.com`, `bbtentertainment.com`, `finvix.in`,
  `petabyte.in`, `1paisakart.com`). `~/public_html` is a **symlink to maslindia.com** — never
  touch it. Everything for this app lives under `~/domains/1paisakart.com/`. `deploy.sh`
  refuses to run if its web-root path isn't exactly ours.
- **CLI `php` is 7.4.** Dependencies need >= 8.4.1, so every command uses
  `/opt/alt/php84/usr/bin/php`, including composer:
  `/opt/alt/php84/usr/bin/php /usr/local/bin/composer ...`
- **Web PHP defaulted to 8.3**, which fails composer's platform check. Fixed with a
  CloudLinux/LiteSpeed handler on the first line of `.htaccess`:
  `AddHandler application/x-httpd-alt-php84___lsphp .php`
  Re-adding this is in `deploy.sh`'s path only via `.htaccess`, which is NOT overwritten by
  a deploy — if you ever replace `.htaccess`, put that line back or the site 500s.
- **No node/npm.** The frontend is built locally and uploaded (same as the old host).
- **No root, no systemctl, no web-server config.** PHP version per domain is otherwise an
  hPanel setting (Advanced → PHP Configuration).

## Redeploy
**Backend** — push to GitHub, then:
```bash
ssh -i ~/.ssh/paisa_hostinger -p 65002 u566989246@82.180.152.250 'bash ~/apps/1paisakart/deploy.sh'
```
(`~/apps/1paisakart/deploy.sh` = [`deploy-hostinger.sh`](../deploy-hostinger.sh): git pull →
rsync → composer → rewrite index.php → migrate → recache.)

**Frontend** — built locally, two uploads:
```bash
cd frontend && npm run build
scp -i ~/.ssh/paisa_hostinger -P 65002 dist/assets/* \
  u566989246@82.180.152.250:~/domains/1paisakart.com/public_html/assets/
scp -i ~/.ssh/paisa_hostinger -P 65002 dist/index.html \
  u566989246@82.180.152.250:~/apps/1paisakart/public/spa.html
```
`spa.html` lives in the **app's** `public/`, not the web root — Laravel's fallback route
reads it from disk and serves it, so it is never fetched directly.

## .env
Lives only at `~/apps/1paisakart/.env` (mode 600), never in git. Beyond the usual keys:
- `UPLOADS_ROOT=/home/u566989246/domains/1paisakart.com/public_html/uploads` — images must be
  written somewhere Apache serves, now that the app is outside the web root. `UPLOADS_URL=/uploads`.
- `SITE_PASSWORD` — the private-site gate. Empty = public.
- `SANCTUM_STATEFUL_DOMAINS=1paisakart.com,www.1paisakart.com` — login fails on any origin
  not listed here, including a bare IP.
- DB credentials can be set interactively with `bash ~/apps/1paisakart/set-db-creds.sh`,
  which tests the connection before writing.

---

# Deployment — GoDaddy cPanel (OLD server, retired 2026-10-27)

Live: **https://1paisakart.com** — addon domain, docroot `~/public_html/1paisakart.com`,
Laravel app in `~/onepaisakart` (outside web root). PHP 8.4, MySQL, SFTP-only (no SSH shell).

## Layout on the server
```
~/onepaisakart/                     Laravel app (vendor/, .env, storage/, public/spa.html …)
~/public_html/1paisakart.com/       web root:
    index.php                       front controller → requires ~/onepaisakart (absolute path)
    .htaccess                       Laravel's public/.htaccess
    assets/                         Vue build output (served as static files)
    favicon.svg, icons.svg
```
`routes/web.php` has a `Route::fallback()` that returns `public/spa.html` for any non-API
route, so Laravel serves the Vue SPA and the API from one origin (Sanctum cookies "just work").

## Redeploy (no shell) — the flow used
1. Local: `composer install --no-dev --optimize-autoloader` (backend), `npm run build` (frontend).
2. Assemble: app → `onepaisakart/` (with `public/spa.html` = the Vue `dist/index.html`);
   docroot files (`index.php`, `.htaccess`, `assets/`, favicons).
3. `zip -r onepaisakart-app.zip onepaisakart` and SFTP it to `~`.
4. Extract server-side: a tiny token-guarded `unzip.php` (uses `ZipArchive`) hit over HTTPS,
   then deleted. (Or use cPanel File Manager → Extract.)
5. SFTP the docroot files into `public_html/1paisakart.com/` (delete the placeholder `index.html`).
6. Upload the production `.env` (SESSION_DRIVER=file, DB creds, `APP_URL`/`SANCTUM_STATEFUL_DOMAINS`).
7. Migrate + seed via the token-guarded `GET /__deploy?token=…` route (shell-less DB setup),
   then disable it by removing `DEPLOY_TOKEN` from `.env` (route returns 403 without a token).

## Redeploy after the first deploy (git-based, backend)
SSH is now enabled (key at `~/.ssh/paisa_deploy` locally). The server has `git`, `composer`,
and PHP 8.4 — but **no node**. So the backend redeploys from git; the frontend is built locally.

**Backend** — push to GitHub, then on the server:
```bash
ssh -i ~/.ssh/paisa_deploy be4dkf29ol96@bom1plzcpnl503742.prod.bom1.secureserver.net
bash ~/deploy.sh        # git pull → rsync → composer --no-dev → migrate --force → recache
```
(`~/deploy.sh` = [`deploy-cpanel.sh`](../deploy-cpanel.sh); pulls into `~/deploy-src`, syncs to `~/onepaisakart`.)

**Frontend** — build locally and upload the two outputs:
```bash
cd frontend && npm run build
# dist/assets/*      -> public_html/1paisakart.com/assets/   (SFTP)
# dist/index.html    -> onepaisakart/public/spa.html          (SFTP)
```

**Production caches**: `config:cache` + `view:cache` + `event:cache` are applied (via the direct
PHP binary). `route:cache` is skipped — some routes are closures. Re-run `deploy.sh` after any
`.env` change so the config cache is rebuilt.

## Notes / gotchas
- **SESSION_DRIVER=file**, not database — otherwise every request needs the `sessions` table,
  which doesn't exist until migrations run (chicken-and-egg with the deploy route).
- `.env` is **not** in git; it lives only on the server. `APP_DEBUG=false` in production.
- No `config:cache`/`route:cache` (no shell) — the app runs fine without them, just re-reads `.env`.
- Docroot `index.php` uses the **absolute** app path `/home/<user>/onepaisakart`.
