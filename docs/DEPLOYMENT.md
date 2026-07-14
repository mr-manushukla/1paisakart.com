# Deployment — GoDaddy cPanel (shell-less)

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

## Notes / gotchas
- **SESSION_DRIVER=file**, not database — otherwise every request needs the `sessions` table,
  which doesn't exist until migrations run (chicken-and-egg with the deploy route).
- `.env` is **not** in git; it lives only on the server. `APP_DEBUG=false` in production.
- No `config:cache`/`route:cache` (no shell) — the app runs fine without them, just re-reads `.env`.
- Docroot `index.php` uses the **absolute** app path `/home/<user>/onepaisakart`.
