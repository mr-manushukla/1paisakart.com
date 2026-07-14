# Business Rules — 1paisakart.com (authoritative)

All money is stored and computed as **integer paise** (₹1 = 100 paise). No floats in money math.
`listed_price` below means the product's price in paise.

## 1. Products & pricing
- A product belongs to one **vendor** and one **category**.
- `listed_price` = **vendor payout + platform fee**. Platform fee is a configurable **percentage** (`platform_fee_pct`, global default, overridable per product).
- A product can enable either or both buying modes: `allow_full_buy`, `allow_draw`.

## 2. Model A — 100% Buy
- Customer pays the full `listed_price`.
- **Wallet credit** may be applied, capped at **10% of the item's price** (`floor(listed_price * 0.10)`), limited further by the wallet balance. Remainder paid via gateway.
- On payment success → `orders` + `order_items`, stock decremented, vendor payout accrued.
- Wallet applied on a 100% buy debits the wallet ledger (a `purchase_debit` transaction).

## 3. Model B — 1% Draw (lucky draw)
- Product draws run in **batches of exactly 100 entries**.
- Entry cost = **1% of `listed_price`** = `floor(listed_price / 100)` paise, per entry. (Rounding remainder, if any, is absorbed by the platform fee — never charge the customer more than 1%.)
- Payment for an entry is **real money only** — wallet credit can NOT be used to enter a draw.
- A user may hold at most **one active entry per open batch** (configurable via `max_entries_per_user`, default 1).
- When the **100th** entry is added:
  1. Batch atomically transitions `open → filled` (pessimistic row lock; the fill happens exactly once).
  2. A single **winner** is chosen with `random_int` over the 100 entries.
  3. Winner's entry → `won`. Winner receives the product (a fulfilled order at their 1% cost). No further charge to the winner.
  4. The other **99 entries → refunded**: each gets a **restricted** wallet credit equal to their 1% entry cost.
  5. Pool (100 × 1% = `listed_price`) settles vendor payout + platform fee.
  6. Batch → `drawn`. A fresh `open` batch may be opened for the product on demand.
- If a batch never fills and is **cancelled** by admin/vendor: all entries refunded as restricted wallet credit (same rule as losers).

## 4. Wallet (restricted credit)
- Source of truth: `wallet_transactions` ledger. `users.wallet_balance` is a cached mirror, written in the **same DB transaction** as the ledger row.
- Credit types: `draw_refund` (restricted), `admin_adjust`.
- Debit types: `purchase_debit`.
- **Restriction:** wallet credit is spendable **only** on a 100% buy, and only up to **10% of the item's price** per item. It can **never** fund a 1% draw entry.
- Ledger rows are **append-only**. Never mutate a past transaction; correct with a new offsetting row.

## 5. Roles & ownership
- **admin** (single): manages categories, vendors, platform fee, can cancel batches, sees everything.
- **vendor**: manages own products & batches, sees own orders/entries/payouts.
- **customer**: buys, enters draws, has a wallet.

## 6. Transparency (explicit requirement)
- For any product with an open batch, a **public** endpoint returns: `filled/100`, and the participant list with **masked** display names (e.g. `Ma***la`). The pool is fully visible to anyone.

## 7. Invariants (enforced + tested)
- `sum(wallet_transactions.amount for user) == users.wallet_balance` always.
- A filled batch has exactly **1** `won` entry and **99** `refunded` entries.
- No draw entry is ever paid with wallet credit.
- Wallet applied to any single 100% item ≤ `floor(item_price * 0.10)`.
- No batch is drawn twice.
