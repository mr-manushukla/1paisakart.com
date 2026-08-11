# 1paisakart.com

Multi-vendor e-commerce with two buying models: a **1% lucky-draw** and a normal **100% buy**.

- Backend: **Laravel 13** (PHP 8.5) REST API — `backend/`
- Frontend: **Vue 3 + Vite + Pinia + Vue Router + Tailwind v4** SPA — `frontend/`
- DB: **MySQL**, Auth: **Sanctum** (SPA, same-site cookies)
- Design: recreate the green e-commerce look of gramentheme *Addina* (own assets, no scraped files)

## Roles
Single **admin**, many **vendors**, many **customers**. `users.role` enum — no permissions package (KISS; add Spatie only if roles get granular).

## The two buying models (money core — never guess here)
See `docs/BUSINESS_RULES.md` for the authoritative spec. Summary:

1. **100% Buy** — pay full listed price. Wallet credit may cover **≤1% of the item price**; rest is real money.
2. **Lucky Draw (1% advance)** — pools are per **price-band club**, *not* per product, so similar-priced
   products share a pool. Pay a **1% advance** (real money only) to book a seat. At **100 seats** the pool
   draws **one winner** (1 in 100) who keeps **the product they booked** — the 1% covers it and the
   **platform absorbs the balance**. The other 99 are **not auto-refunded**: they get a **7-day window** to
   either **pay the remaining 99%** (Option A) or **move the 1% to wallet** (Option B). No choice → Option B.
   The winner then **claims** the prize — delivery address + **TDS at `tds_pct`** (default 30%, s.194B) on
   the prize value — before it is dispatched. Claim is exactly-once; the order sits at `pending` until then.

### Wallet rules (restricted credit)
- Usable on a purchase, capped at **1% of that item's price** (`wallet_cap_pct`).
- **Never** usable to pay a 1% booking advance.
- Balance is a **ledger** (`wallet_transactions`) + cached `users.wallet_balance`, both written in one DB transaction.

### Draw integrity
- Adding the 100th seat must atomically close the pool and draw **exactly once** — pessimistic row lock on the batch inside a DB transaction.
- Winner via `random_int` server-side. Never client-supplied.

### Transparency
- Every open club pool exposes a **public** endpoint: fill progress (X/100), odds, and the participant list
  (masked names **+ the product each seat booked**). The API always serves this — never gate the endpoint.
- **The storefront reveals it only once the pool is ≥60% full** (`frontend/src/lib/pool.js`). Below that the
  progress bar *and* the "Who's in the pool" seat map are hidden, so a half-empty pool doesn't read as dead;
  above it, both appear with an urgency line. A customer always sees the fill level of pools they've joined
  (My Draws / My Orders), and admin/vendor dashboards are never gated.

## Coding rules
- **KISS / DRY / SOLID**, ponytail-lazy: climb the ladder — reuse Laravel/Vue built-ins before writing code, one line before fifty, no speculative abstractions.
- Money/security paths: **not** lazy. Validate at the boundary, wrap ledger writes in transactions, leave one runnable check.
- Business rules live in **service classes** (`app/Services`), not controllers. Controllers are thin.
- Money stored as **integer paise** (never floats).

## Commands
```bash
# backend
cd backend && php artisan serve            # http://127.0.0.1:8000
php artisan migrate:fresh --seed
php artisan test

# frontend
cd frontend && npm run dev                 # http://127.0.0.1:5173
```
