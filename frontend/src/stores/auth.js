import { defineStore } from 'pinia'
import api, { csrf } from '../lib/api'

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
      } catch {
        this.user = null
      } finally {
        this.ready = true
      }
    },
    async login(email, password) {
      await csrf()
      const { data } = await api.post('/login', { email, password })
      this.user = data.data
    },
    async register(payload) {
      await csrf()
      const { data } = await api.post('/register', payload)
      this.user = data.data
    },
    async logout() {
      await api.post('/logout')
      this.user = null
    },
    // Keep wallet_balance fresh after a draw/checkout without a full reload.
    async refresh() {
      await this.fetchMe()
    },
  },
})
