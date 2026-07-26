<script setup>
import { watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWishlistStore } from '../stores/wishlist'
import { money } from '../lib/money'

/** Slide-in account menu for mobile. Desktop keeps the dropdown in the header. */
const open = defineModel({ type: Boolean, default: false })
const emit = defineEmits(['logout'])

const auth = useAuthStore()
const wishlist = useWishlistStore()
const route = useRoute()

// Navigating always dismisses the drawer, however the route changed.
watch(() => route.fullPath, () => { open.value = false })

const links = [
  { to: '/profile', icon: '👤', label: 'My Profile', customer: true },
  { to: '/wishlist', icon: '♥', label: 'My Wishlist' },
  { to: '/orders', icon: '🧾', label: 'My Orders', customer: true },
  { to: '/addresses', icon: '📍', label: 'My Addresses', customer: true },
  { to: '/my-draws', icon: '🎯', label: 'My Draws', customer: true },
  { to: '/wallet', icon: '👛', label: '1% Wallet', customer: true },
]
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 md:hidden">
      <!-- scrim -->
      <div class="absolute inset-0 bg-black/40" @click="open = false" />

      <aside
        class="absolute inset-y-0 left-0 flex w-[82%] max-w-xs flex-col bg-white shadow-xl"
        role="dialog"
        aria-modal="true"
        aria-label="Account menu"
      >
        <!-- Who's signed in -->
        <div class="flex items-center gap-3 bg-gradient-to-br from-brand-600 to-brand-800 p-4 text-white">
          <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-white/20 font-bold">
            {{ auth.isAuthed ? (auth.user.name?.[0] ?? '?').toUpperCase() : '👋' }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate font-semibold">{{ auth.isAuthed ? auth.user.name : 'Welcome' }}</p>
            <p v-if="auth.isAuthed && auth.isCustomer" class="text-xs text-white/80">1% Wallet {{ money(auth.walletBalance) }}</p>
            <p v-else-if="!auth.isAuthed" class="text-xs text-white/80">Sign in to shop and join draws</p>
          </div>
          <button class="text-2xl leading-none text-white/80" aria-label="Close menu" @click="open = false">×</button>
        </div>

        <nav class="flex-1 overflow-y-auto py-2 text-sm">
          <template v-if="auth.isAuthed">
            <RouterLink
              v-for="l in links"
              v-show="!l.customer || auth.isCustomer"
              :key="l.to"
              :to="l.to"
              class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50"
            >
              <span class="w-5 text-center">{{ l.icon }}</span>
              <span class="flex-1">{{ l.label }}</span>
              <span v-if="l.to === '/wishlist' && wishlist.count" class="chip bg-rose-50 text-rose-600">{{ wishlist.count }}</span>
            </RouterLink>

            <RouterLink v-if="auth.isVendor" to="/vendor" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
              <span class="w-5 text-center">🏪</span><span>Vendor dashboard</span>
            </RouterLink>
            <RouterLink v-if="auth.isAdmin" to="/admin" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
              <span class="w-5 text-center">🛠️</span><span>Admin dashboard</span>
            </RouterLink>
          </template>

          <template v-else>
            <RouterLink to="/login" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50"><span class="w-5 text-center">→</span>Sign in</RouterLink>
            <RouterLink to="/register" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50"><span class="w-5 text-center">✚</span>Create account</RouterLink>
            <RouterLink to="/wishlist" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50"><span class="w-5 text-center">♥</span>My Wishlist</RouterLink>
          </template>

          <div class="my-2 border-t border-slate-100" />
          <RouterLink to="/shop" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50"><span class="w-5 text-center">🛍️</span>Shop</RouterLink>
          <RouterLink to="/winners" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50"><span class="w-5 text-center">🏆</span>Winners</RouterLink>
        </nav>

        <button
          v-if="auth.isAuthed"
          class="border-t border-slate-100 px-4 py-3 text-left text-sm font-semibold text-rose-600 hover:bg-rose-50"
          @click="emit('logout')"
        >
          ⏻ Logout
        </button>
      </aside>
    </div>
  </Teleport>
</template>
