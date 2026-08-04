import { defineStore } from 'pinia'

// Each account keeps its OWN cart in localStorage, so a cart survives logout and
// is waiting at the next sign-in — while never showing up for a different user.
const keyFor = (userId) => (userId ? `paisa_cart_u${userId}` : 'paisa_cart_guest')
let activeKey = keyFor(null)

const read = (k) => { try { return JSON.parse(localStorage.getItem(k)) ?? [] } catch { return [] } }
const write = (k, items) => localStorage.setItem(k, JSON.stringify(items))
const load = () => read(activeKey)

// A line is a normal purchase ('buy'), a 1% advance booking ('draw'), or the
// remaining 99% on a booking that didn't win ('balance'). The same product can
// sit in the cart under several modes, so lines are keyed by mode + id.
// For 'balance' the id is the DRAW ENTRY id, not the product id — one entry is
// one specific seat to settle.
const keyOf = (mode, id) => `${mode}:${id}`

export const useCartStore = defineStore('cart', {
  state: () => ({ items: load() }),
  getters: {
    buyItems: (s) => s.items.filter((i) => i.mode === 'buy' || !i.mode),
    drawItems: (s) => s.items.filter((i) => i.mode === 'draw'),
    balanceItems: (s) => s.items.filter((i) => i.mode === 'balance'),
    count: (s) => s.items.reduce((n, i) => n + (i.mode === 'buy' || !i.mode ? i.qty : 1), 0),
    /** Full-price portion only. */
    subtotal: (s) => s.items.filter((i) => i.mode === 'buy' || !i.mode).reduce((n, i) => n + i.listed_price * i.qty, 0),
    /** 1% advances — real money only, wallet can never pay these. */
    drawTotal: (s) => s.items.filter((i) => i.mode === 'draw').reduce((n, i) => n + (i.entry_price ?? 0), 0),
    /** Remaining 99% on bookings being settled from the cart. */
    balanceTotal: (s) => s.items.filter((i) => i.mode === 'balance').reduce((n, i) => n + (i.balance_due ?? 0), 0),
    /** Sum of per-item wallet caps across the purchase lines. */
    walletCap: (s) => s.items.filter((i) => i.mode === 'buy' || !i.mode)
      .reduce((n, i) => n + (i.max_wallet_applicable ?? 0) * i.qty, 0),
    has: (s) => (mode, id) => s.items.some((i) => i.key === keyOf(mode, id)),
  },
  actions: {
    persist() { write(activeKey, this.items) },

    /**
     * Point the cart at a user's own storage (null = signed out).
     * Anything added while browsing signed-out is merged in on sign-in, so a
     * cart built before logging in isn't lost.
     */
    switchUser(userId) {
      const nextKey = keyFor(userId)
      if (nextKey === activeKey) return

      let items = read(nextKey)

      if (userId) {
        const guest = read(keyFor(null))
        if (guest.length) {
          guest.forEach((g) => {
            const existing = items.find((i) => i.key === g.key)
            if (existing) {
              if (existing.mode !== 'draw') existing.qty += g.qty
            } else {
              items.push(g)
            }
          })
          write(keyFor(null), [])   // guest cart has been handed over
        }
        write(nextKey, items)
      }

      activeKey = nextKey
      this.items = items
    },

    /** @param {'buy'|'draw'} mode */
    add(product, qty = 1, mode = 'buy') {
      const key = keyOf(mode, product.id)
      const line = this.items.find((i) => i.key === key)

      if (line) {
        // A draw line is always exactly one seat (one seat per product per pool).
        if (mode !== 'draw') line.qty += qty
      } else {
        this.items.push({
          key,
          mode,
          product_id: product.id,
          slug: product.slug,
          name: product.name,
          image: product.image,
          listed_price: product.listed_price,
          entry_price: product.entry_price,
          max_wallet_applicable: product.max_wallet_applicable,
          qty: mode === 'draw' ? 1 : qty,
        })
      }
      this.persist()
    },

    /**
     * Queue the remaining 99% on a booking that didn't win, so several can be
     * settled in one checkout instead of paying entry by entry.
     * @param {{id:number, balance_due:number, product?:object}} entry
     */
    addBalance(entry) {
      const key = keyOf('balance', entry.id)
      if (this.items.some((i) => i.key === key)) return

      this.items.push({
        key,
        mode: 'balance',
        entry_id: entry.id,
        product_id: entry.product?.id ?? null,
        slug: entry.product?.slug,
        name: entry.product?.name,
        image: entry.product?.image,
        listed_price: entry.product?.listed_price,
        balance_due: entry.balance_due,
        qty: 1,
      })
      this.persist()
    },

    setQty(key, qty) {
      const line = this.items.find((i) => i.key === key)
      if (line && line.mode !== 'draw') { line.qty = Math.max(1, qty); this.persist() }
    },
    remove(key) { this.items = this.items.filter((i) => i.key !== key); this.persist() },
    removeDrawItems() { this.items = this.items.filter((i) => i.mode !== 'draw'); this.persist() },
    clear() { this.items = []; this.persist() },
  },
})
