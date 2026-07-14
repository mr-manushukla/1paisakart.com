import { defineStore } from 'pinia'
import api from '../lib/api'
import { useAuthStore } from './auth'

// Guests can save (localStorage). On login we merge local ↔ server.
const KEY = 'paisa_wishlist'
const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) ?? [] } catch { return [] } }

export const useWishlistStore = defineStore('wishlist', {
  state: () => ({ items: load() }), // full product objects (enough for a card)
  getters: {
    count: (s) => s.items.length,
    has: (s) => (id) => s.items.some((p) => p.id === id),
  },
  actions: {
    persist() { localStorage.setItem(KEY, JSON.stringify(this.items)) },

    async toggle(product) {
      const auth = useAuthStore()
      const wished = this.has(product.id)
      if (wished) this.items = this.items.filter((p) => p.id !== product.id)
      else this.items.push(product)
      this.persist()

      if (auth.isCustomer) {
        try { await api.post(`/products/${product.slug}/wishlist`) } catch { /* keep optimistic local state */ }
      }
      return !wished
    },

    // After login: pull server saves in, push local-only saves up.
    async syncOnLogin() {
      const auth = useAuthStore()
      if (!auth.isCustomer) return
      try {
        const { data } = await api.get('/wishlist')
        const serverIds = new Set(data.data.map((p) => p.id))
        for (const p of data.data) if (!this.has(p.id)) this.items.push(p)
        for (const it of [...this.items]) {
          if (!serverIds.has(it.id)) { try { await api.post(`/products/${it.slug}/wishlist`) } catch {} }
        }
        this.persist()
      } catch {}
    },

    clearLocal() { this.items = []; this.persist() },
  },
})
