# 1paisakart.com

Multi-vendor e-commerce with **two buying models**:

1. **100% Buy** — pay the full price. Wallet credit may cover up to **10% of each item's price**.
2. **1% Draw** — pay **1%** to take a seat in a **100-person pool**. When it fills, **one random winner** keeps the product for their 1%; the other **99 are refunded to their wallet**. Every pool is **public** (transparency by design).

Single **admin**, many **vendors**, many **customers**. Money is stored as integer **paise**.

## Stack
- **Backend** — Laravel 13 REST API (Sanctum SPA cookie auth), `backend/`
- **Frontend** — Vue 3 + Vite + Pinia + Vue Router + Tailwind v4, `frontend/`
- **DB** — SQLite for local dev (zero-config); MySQL is a one-line `.env` switch (see below)

See [`docs/BUSINESS_RULES.md`](docs/BUSINESS_RULES.md) for the authoritative money spec and [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the data model and API.

## Run it
```bash
# Backend  → http://localhost:8000
cd backend
composer install
php artisan migrate:fresh --seed
php artisan serve

# Frontend → http://localhost:5173  (proxies /api + /sanctum to :8000)
cd frontend
npm install
npm run dev
```

## Demo accounts (password: `password`)
| Role | Email |
|------|-------|
| Admin | `admin@1paisakart.test` |
| Vendor | `vendor1@1paisakart.test`, `vendor2@1paisakart.test` |
| Customer | `customer1@1paisakart.test` … `customer50@1paisakart.test` |

Seed opens a live 42/100 draw on **Smart Watch Series X** so the transparency page has a real pool.

## Tests (money-core invariants)
```bash
cd backend && php artisan test
```
Covers: 1 winner + 99 refunds per batch, wallet ledger consistency, draws never spend wallet, the 10% wallet cap on 100% buys, and batch cancellation refunds.

## Switch to MySQL
One command (you enter your own MySQL root password once — it's never stored):
```bash
bash backend/setup-mysql.sh
```
It creates the `onepaisakart` DB + a scoped `paisa` user, points `backend/.env` at MySQL, and runs `migrate:fresh --seed`. Then restart `php artisan serve`.

MySQL's InnoDB row locks make the concurrent draw-fill fully safe (SQLite serializes writes, which is also correct, just less concurrent).
