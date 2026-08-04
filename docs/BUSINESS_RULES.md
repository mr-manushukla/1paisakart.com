# Business Rules — 1paisakart.com (authoritative)

All money is stored and computed as **integer paise** (₹1 = 100 paise). No floats in money math.
`listed_price` below means the product's price in paise.

## 1. Products & pricing
- A product belongs to one **vendor** and one **category**.
- `listed_price` = **vendor payout + platform fee** (`platform_fee_pct`, global default, overridable per product).
- A product can enable either or both buying modes: `allow_full_buy`, `allow_draw`.

## 2. Model A — 100% Buy
- Customer pays the full `listed_price`.
- **Wallet credit** may be applied, capped at **1% of the item's price** (`wallet_cap_pct`), limited further
  by the wallet balance. Remainder paid via gateway.
- Wallet applied on a buy debits the ledger (`purchase_debit`).

## 3. Model B — Lucky Draw Purchase Scheme (1% advance)

### Clubs (price bands)
Draw pools are **per price-band club**, *not* per product — so two different products of similar price
share one pool (e.g. a ₹1L phone and a ₹1L laptop). Bands: `100–1000`, `1001–5000`, then **₹5,000 steps
up to ₹5,00,000** (101 clubs, seeded by migration). A product's club is resolved from its `listed_price`.

### Seats
- Booking one product = **one seat**. A customer may hold **several seats in the same pool**, but each
  must be a **different product** in that price band — the **same item can never be booked twice** by the
  same customer in the same pool (enforced by a unique index on `batch_id + user_id + product_id`).
- `max_entries_per_user` caps how many distinct items one customer may hold in a pool. It defaults to
  **0 = uncapped** (only pool availability limits a buyer); set a number to reinstate a ceiling.
- A winner receives **exactly one item** (the product their winning seat booked). Any **other** seats
  that winner holds are **not** auto-refunded — they get the **same choice** as every other participant
  (buy at the remaining 99%, or move the 1% to wallet). Winning one item never cancels the buyer's
  other intended purchases.

### Flow
1. **Secure your entry** — customer pays a **1% advance** (`floor(listed_price / 100)`) to book a seat.
   The advance is **real money only** — wallet credit can never pay it. Participation requires the advance.
2. **The draw** — a club pool holds **100 seats**. When the 100th seat is booked the pool closes and draws
   **one winner** (odds **1 in 100**).
3. **If you win** — you receive **the product you booked**. Your 1% covers the total cost; no further
   payment (government taxes on the prize value are the winner's responsibility). The **platform absorbs**
   the balance so the vendor is paid in full. Subsidy is derivable as `subtotal - wallet_applied - payable`.
4. **If you don't win** — the advance is **not** auto-refunded. The entry becomes `lost_pending` with a
   **7-day choice window** (`choice_window_days`), and the customer picks:
   - **Option A — Purchase**: pay the remaining 99%; the 1% is fully adjusted (`payable = listed_price - advance`).
   - **Option B — Wallet credit**: the 1% moves to their wallet, usable toward any other product.
   - **No choice in time** → Option B is applied automatically (`draws:auto-credit`, scheduled hourly).
- If a pool is **cancelled** by an admin, every active advance is credited back to wallet.

### Entry states
`active` → `won` | `lost_pending` → (`converted` | `credited`) ; `refunded` when a pool is cancelled.

## 4. Wallet (restricted credit)
- Source of truth: `wallet_transactions` ledger. `users.wallet_balance` is a cached mirror written in the
  **same DB transaction**.
- Credit types: `draw_refund`, `admin_adjust`. Debit: `purchase_debit`.
- **Restriction:** spendable on a purchase up to **1% of that item's price**; it can **never** pay a 1%
  booking advance.
- Ledger rows are **append-only** — correct with an offsetting row, never mutate.

## 5. Roles & ownership
- **admin** (single): categories, vendors, platform fee, may cancel pools, sees everything.
- **vendor**: own products/orders; club pools are **read-only** (a pool is shared across vendors).
- **customer**: buys, books draws, has a wallet.

## 6. Transparency (explicit requirement)
- Every open club pool exposes a **public** endpoint: `filled/100`, the odds, and the participant list with
  **masked** names **plus the product each seat booked** and the advance paid.

## 7. Invariants (enforced + tested)
- `sum(wallet_transactions.amount for user) == users.wallet_balance` always.
- A drawn pool has exactly **1** `won` entry; **every** other seat — including the winner's own extra
  seats — is `lost_pending` with a choice deadline. Nothing is auto-credited at draw time.
- No customer holds two seats for the same product in one pool.
- The winner's order is for **the product that entry booked**, with `payable == advance`.
- No booking advance is ever paid from wallet credit.
- Wallet applied to a single item ≤ `floor(item_price * wallet_cap_pct / 100)`.
- No pool is drawn twice.
