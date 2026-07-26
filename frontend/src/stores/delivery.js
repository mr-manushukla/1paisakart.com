import { defineStore } from 'pinia'

// The chosen delivery PIN persists across visits, like Amazon's location picker.
const KEY = 'paisa_pincode'

export const useDeliveryStore = defineStore('delivery', {
  state: () => ({ pincode: localStorage.getItem(KEY) || '' }),
  getters: {
    /** Only a complete 6-digit PIN filters the catalogue. */
    active: (s) => /^\d{6}$/.test(s.pincode),
  },
  actions: {
    set(pin) {
      const clean = String(pin ?? '').replace(/\D/g, '').slice(0, 6)
      this.pincode = clean
      if (clean) localStorage.setItem(KEY, clean)
      else localStorage.removeItem(KEY)
    },
    clear() { this.set('') },
    /** Spread into API params — omitted entirely when no PIN is set. */
    params() { return this.active ? { pincode: this.pincode } : {} },
  },
})
