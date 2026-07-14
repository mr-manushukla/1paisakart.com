import { defineStore } from 'pinia'

const KEY = 'paisa_cart'
const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) ?? [] } catch { return [] } }

export const useCartStore = defineStore('cart', {
  state: () => ({ items: load() }),
  getters: {
    count: (s) => s.items.reduce((n, i) => n + i.qty, 0),
    subtotal: (s) => s.items.reduce((n, i) => n + i.listed_price * i.qty, 0),
    // Sum of per-item 10% caps — the most wallet credit this cart can absorb.
    walletCap: (s) => s.items.reduce((n, i) => n + (i.max_wallet_applicable ?? 0) * i.qty, 0),
  },
  actions: {
    persist() { localStorage.setItem(KEY, JSON.stringify(this.items)) },
    add(product, qty = 1) {
      const line = this.items.find((i) => i.product_id === product.id)
      if (line) line.qty += qty
      else this.items.push({
        product_id: product.id,
        slug: product.slug,
        name: product.name,
        image: product.image,
        listed_price: product.listed_price,
        max_wallet_applicable: product.max_wallet_applicable,
        qty,
      })
      this.persist()
    },
    setQty(productId, qty) {
      const line = this.items.find((i) => i.product_id === productId)
      if (line) { line.qty = Math.max(1, qty); this.persist() }
    },
    remove(productId) { this.items = this.items.filter((i) => i.product_id !== productId); this.persist() },
    clear() { this.items = []; this.persist() },
  },
})
