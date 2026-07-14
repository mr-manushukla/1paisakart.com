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

1. **100% Buy** — pay full listed price. Wallet credit may cover **≤10% of the item price**; rest is real money.
2. **1% Draw** — product runs in **batches of 100**. Each entry costs **1% of listed price**. At **100/100** the batch closes and **one random winner** receives the product for their 1%; the other **99 are refunded to wallet**. The pooled 100% = vendor payout + platform fee.

### Wallet rules (restricted credit)
- Refund credit is **restricted**: usable **only** on a 100% buy, capped at **10% of that item's price**.
- **Never** usable to enter a 1% draw.
- Balance is a **ledger** (`wallet_transactions`) + cached `users.wallet_balance`, both written in one DB transaction.

### Draw integrity
- Adding the 100th entry must atomically close the batch and trigger the draw **exactly once** — pessimistic row lock on the batch inside a DB transaction.
- Winner via `random_int` server-side. Never client-supplied.

### Transparency
- Every open batch exposes a **public** endpoint: fill progress (X/100) + participant list (masked names). "Who is in the pool" is fully visible.

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
