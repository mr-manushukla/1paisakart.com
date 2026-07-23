import { defineStore } from 'pinia'
import api, { csrf } from '../lib/api'
import { useWishlistStore } from './wishlist'
import { useCartStore } from './cart'

// localStorage is shared by every account using this browser. The cart is stored
// per user, so it survives logout and returns at the next sign-in without ever
// leaking to someone else. The wishlist is server-backed, so it just re-syncs.
function useCartFor(userId) {
  useCartStore().switchUser(userId)
}

export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null, ready: false }),
  getters: {
    isAuthed: (s) => !!s.user,
    role: (s) => s.user?.role ?? null,
    isCustomer: (s) => s.user?.role === 'customer',
    isVendor: (s) => s.user?.role === 'vendor',
    isAdmin: (s) => s.user?.role === 'admin',
    walletBalance: (s) => s.user?.wallet_balance ?? 0,
    homeRouteName: (s) => ({ vendor: 'vendor', admin: 'admin' }[s.user?.role] ?? 'home'),
  },
  actions: {
    async fetchMe() {
      try {
        const { data } = await api.get('/me')
        this.user = data.data
        useCartFor(this.user.id)          // their own saved cart
        useWishlistStore().syncOnLogin()  // merges any guest saves, then mirrors the server
      } catch {
        this.user = null
        useCartFor(null)                  // stale session → back to the guest cart
      } finally {
        this.ready = true
      }
    },
    async login(email, password) {
      await csrf()
      const { data } = await api.post('/login', { email, password })
      this.user = data.data
      useCartFor(this.user.id)
      useWishlistStore().syncOnLogin()
    },
    async register(payload) {
      await csrf()
      const { data } = await api.post('/register', payload)
      this.user = data.data
      useCartFor(this.user.id)
      useWishlistStore().syncOnLogin()
    },
    async logout() {
      await api.post('/logout')
      this.user = null
      // The cart stays saved under that user's key — it just isn't the active one
      // any more, so the next person sees an empty (guest) cart.
      useCartFor(null)
      useWishlistStore().clearLocal()
    },
    // Keep wallet_balance fresh after a draw/checkout without a full reload.
    async refresh() {
      await this.fetchMe()
    },
  },
})
