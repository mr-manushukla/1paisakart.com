# Architecture — 1paisakart.com

## Layout
```
backend/    Laravel 13 REST API (Sanctum SPA auth, MySQL)
frontend/   Vue 3 SPA (Vite, Pinia, Vue Router, Tailwind v4, axios)
docs/       Rules & architecture (this)
```

## Backend layering (SOLID: thin controllers, logic in services)
```
Http/Controllers   → validate (Form Requests) + call a service + return a Resource
Services           → business rules (WalletService, DrawService, CheckoutService)
Models             → Eloquent + relationships + casts (money = integer paise)
Policies           → role/ownership authorization
Jobs               → async draw settlement (queue: database driver)
```

## Data model (money columns = unsigned bigint paise)
- **users** `role(admin|vendor|customer)`, `wallet_balance`, name, email, password
- **shops** `user_id`, name, slug, description  (one per vendor)
- **categories** `name`, `slug`, `parent_id?`
- **products** `shop_id`, `category_id`, `name`, `slug`, `description`, `listed_price`,
  `stock`, `allow_full_buy`, `allow_draw`, `platform_fee_pct?`, `status`, images
- **draw_batches** `product_id`, `batch_no`, `size(=100)`, `entry_price`, `status(open|filled|drawn|cancelled)`,
  `filled_count`, `winner_entry_id?`, `drawn_at?`
- **draw_entries** `batch_id`, `user_id`, `amount`, `status(active|won|refunded)`
- **orders** `user_id`, `subtotal`, `wallet_applied`, `payable`, `status`, `source(buy|draw_win)`
- **order_items** `order_id`, `product_id`, `qty`, `unit_price`, `line_total`
- **wallet_transactions** `user_id`, `type`, `amount(±)`, `balance_after`, `ref_type`, `ref_id`
- **payments** `order_id?`, `entry_id?`, `amount`, `gateway`, `status`, `ref`  (gateway stubbed for now)

## Key services
- **WalletService**: `credit()`, `debit()`, `applicableTo100Buy(item)` (≤10% cap) — all ledger writes in a DB transaction, updates cached balance.
- **DrawService**: `enter(product,user)` (real money only, locks batch, opens next batch as needed), `settle(batch)` (winner pick + 99 refunds, exactly once).
- **CheckoutService**: `place(user, cart, applyWallet)` — 100% buy, applies wallet ≤10%/item.

## API (prefix `/api`)
```
POST /register  /login  /logout            (Sanctum)
GET  /products  /products/{slug}           public catalog
GET  /products/{slug}/batch                public: open batch fill + masked participants (transparency)
POST /products/{slug}/enter-draw           auth customer: pay 1%, join batch
POST /checkout                             auth customer: 100% buy (+optional wallet)
GET  /wallet  /wallet/transactions         auth
GET  /orders                               auth
# vendor
GET/POST/PUT  /vendor/products             role:vendor, own shop
GET  /vendor/orders  /vendor/batches
# admin
GET/POST /admin/categories                 role:admin
GET  /admin/vendors  /admin/batches        role:admin
POST /admin/batches/{id}/cancel            role:admin
GET  /admin/settings PUT /admin/settings   platform_fee_pct
```

## Frontend (Addina-styled storefront)
Pages: Home, Category/Listing, Product detail (both buy CTAs + live draw progress + participants),
Cart, Checkout, Wallet, Orders, Auth; Vendor dashboard; Admin dashboard.
Pinia stores: `auth`, `cart`, `wallet`. axios instance with Sanctum CSRF + credentials.
