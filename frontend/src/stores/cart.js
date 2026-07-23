import { defineStore } from 'pinia'

// Each account keeps its OWN cart in localStorage, so a cart survives logout and
// is waiting at the next sign-in — while never showing up for a different user.
const keyFor = (userId) => (userId ? `paisa_cart_u${userId}` : 'paisa_cart_guest')
let activeKey = keyFor(null)

const read = (k) => { try { return JSON.parse(localStorage.getItem(k)) ?? [] } catch { return [] } }
const write = (k, items) => localStorage.setItem(k, JSON.stringify(items))
const load = () => read(activeKey)

// A line is either a normal purchase ('buy') or a 1% advance booking ('draw').
// The same product can sit in the cart as both, so lines are keyed by mode+id.
const keyOf = (mode, productId) => `${mode}:${productId}`

export const useCartStore = defineStore('cart', {
  state: () => ({ items: load() }),
  getters: {
    buyItems: (s) => s.items.filter((i) => i.mode !== 'draw'),
    drawItems: (s) => s.items.filter((i) => i.mode === 'draw'),
    count: (s) => s.items.reduce((n, i) => n + (i.mode === 'draw' ? 1 : i.qty), 0),
    /** Full-price portion only. */
    subtotal: (s) => s.items.filter((i) => i.mode !== 'draw').reduce((n, i) => n + i.listed_price * i.qty, 0),
    /** 1% advances — real money only, wallet can never pay these. */
    drawTotal: (s) => s.items.filter((i) => i.mode === 'draw').reduce((n, i) => n + (i.entry_price ?? 0), 0),
    /** Sum of per-item wallet caps across the purchase lines. */
    walletCap: (s) => s.items.filter((i) => i.mode !== 'draw')
      .reduce((n, i) => n + (i.max_wallet_applicable ?? 0) * i.qty, 0),
    has: (s) => (mode, productId) => s.items.some((i) => i.key === keyOf(mode, productId)),
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

    setQty(key, qty) {
      const line = this.items.find((i) => i.key === key)
      if (line && line.mode !== 'draw') { line.qty = Math.max(1, qty); this.persist() }
    },
    remove(key) { this.items = this.items.filter((i) => i.key !== key); this.persist() },
    removeDrawItems() { this.items = this.items.filter((i) => i.mode !== 'draw'); this.persist() },
    clear() { this.items = []; this.persist() },
  },
})
