<script setup>
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import { useWishlistStore } from '../stores/wishlist'
import { money } from '../lib/money'
import { toast } from '../lib/toast'

const auth = useAuthStore()
const cart = useCartStore()
const wishlist = useWishlistStore()
const router = useRouter()
const q = ref('')
const menuOpen = ref(false)

function search() {
  router.push({ name: 'shop', query: q.value ? { q: q.value } : {} })
}
async function logout() {
  await auth.logout()
  menuOpen.value = false
  toast('Signed out')
  router.push({ name: 'home' })
}
</script>

<template>
  <header class="sticky top-0 z-40 border-b border-slate-100 bg-white/95 backdrop-blur">
    <div class="bg-brand-700 text-center text-xs text-white/90">
      <div class="mx-auto max-w-7xl px-4 py-1.5">Pay just 1% to enter a draw • Win big • Transparent pools</div>
    </div>

    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3">
      <RouterLink to="/" class="flex items-center gap-1 font-display text-xl font-extrabold">
        <span class="text-brand-600">1paisa</span><span class="text-accent-500">kart</span>
      </RouterLink>

      <form class="relative hidden flex-1 md:block" @submit.prevent="search">
        <input v-model="q" class="input pl-10" placeholder="Search products…" />
        <span class="absolute left-3 top-2.5 text-slate-400">🔍</span>
      </form>

      <nav class="ml-auto flex items-center gap-4 text-sm font-medium text-slate-600">
        <RouterLink to="/shop" class="hidden hover:text-brand-700 sm:block">Shop</RouterLink>

        <RouterLink v-if="auth.isCustomer" to="/wallet" class="hidden items-center gap-1 hover:text-brand-700 sm:flex">
          <span>👛</span><span class="font-semibold text-brand-700">{{ money(auth.walletBalance) }}</span>
        </RouterLink>

        <RouterLink to="/wishlist" class="relative hover:text-brand-700" aria-label="Saved items">
          <span class="text-rose-500">♥</span>
          <span v-if="wishlist.count" class="absolute -right-2 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ wishlist.count }}</span>
        </RouterLink>

        <RouterLink to="/cart" class="relative hover:text-brand-700">
          🛒
          <span v-if="cart.count" class="absolute -right-2 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-white">{{ cart.count }}</span>
        </RouterLink>

        <div class="relative">
          <button class="btn-ghost px-3 py-1.5 text-sm" @click="menuOpen = !menuOpen">
            {{ auth.isAuthed ? auth.user.name.split(' ')[0] : 'Account' }} ▾
          </button>
          <div v-if="menuOpen" class="absolute right-0 mt-2 w-48 overflow-hidden rounded-xl border border-slate-100 bg-white py-1 shadow-lg" @click="menuOpen = false">
            <template v-if="!auth.isAuthed">
              <RouterLink to="/login" class="block px-4 py-2 hover:bg-slate-50">Sign in</RouterLink>
              <RouterLink to="/register" class="block px-4 py-2 hover:bg-slate-50">Create account</RouterLink>
            </template>
            <template v-else>
              <RouterLink v-if="auth.isCustomer" to="/orders" class="block px-4 py-2 hover:bg-slate-50">My orders</RouterLink>
              <RouterLink v-if="auth.isCustomer" to="/wallet" class="block px-4 py-2 hover:bg-slate-50">Wallet</RouterLink>
              <RouterLink v-if="auth.isVendor" to="/vendor" class="block px-4 py-2 hover:bg-slate-50">Vendor dashboard</RouterLink>
              <RouterLink v-if="auth.isAdmin" to="/admin" class="block px-4 py-2 hover:bg-slate-50">Admin dashboard</RouterLink>
              <button class="block w-full px-4 py-2 text-left text-rose-600 hover:bg-slate-50" @click="logout">Sign out</button>
            </template>
          </div>
        </div>
      </nav>
    </div>
  </header>
</template>
