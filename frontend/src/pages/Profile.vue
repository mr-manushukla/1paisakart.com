<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { money } from '../lib/money'

const auth = useAuthStore()
const user = computed(() => auth.user ?? {})
const initials = computed(() => (user.value.name ?? '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase())
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="mb-6 font-display text-2xl font-bold">My profile</h1>

    <div class="card flex items-center gap-4 p-5">
      <span class="flex h-16 w-16 flex-none items-center justify-center rounded-full bg-brand-600 font-display text-xl font-bold text-white">
        {{ initials }}
      </span>
      <div class="min-w-0">
        <p class="font-display text-xl font-bold">{{ user.name }}</p>
        <p class="truncate text-sm text-slate-500">{{ user.email }}</p>
        <p class="text-sm text-slate-500">{{ user.phone || 'No mobile number saved' }}</p>
      </div>
    </div>

    <div v-if="auth.isCustomer" class="card mt-4 flex items-center justify-between p-5">
      <div>
        <p class="text-sm text-slate-500">1% Wallet balance</p>
        <p class="font-display text-2xl font-extrabold text-brand-700">{{ money(auth.walletBalance) }}</p>
      </div>
      <RouterLink to="/wallet" class="btn-ghost text-sm">View history</RouterLink>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
      <RouterLink to="/orders" class="card p-4 transition hover:shadow-md">
        <p class="font-semibold">🧾 My orders</p>
        <p class="text-sm text-slate-500">Purchases and 1% bookings</p>
      </RouterLink>
      <RouterLink to="/addresses" class="card p-4 transition hover:shadow-md">
        <p class="font-semibold">📍 My addresses</p>
        <p class="text-sm text-slate-500">Manage delivery addresses</p>
      </RouterLink>
      <RouterLink to="/wishlist" class="card p-4 transition hover:shadow-md">
        <p class="font-semibold">♥ My wishlist</p>
        <p class="text-sm text-slate-500">Items you saved for later</p>
      </RouterLink>
      <RouterLink to="/my-draws" class="card p-4 transition hover:shadow-md">
        <p class="font-semibold">🎯 My draws</p>
        <p class="text-sm text-slate-500">Pools you've joined</p>
      </RouterLink>
    </div>
  </div>
</template>
