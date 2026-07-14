<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'

const orders = ref([])
const loading = ref(true)

const statusChip = {
  paid: 'bg-brand-50 text-brand-700',
  fulfilled: 'bg-brand-100 text-brand-800',
  pending: 'bg-amber-50 text-amber-700',
  cancelled: 'bg-rose-50 text-rose-600',
}

onMounted(async () => {
  try {
    const { data } = await api.get('/orders')
    orders.value = data.data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <h1 class="mb-6 font-display text-2xl font-bold">My orders</h1>

    <div v-if="loading" class="card h-40 animate-pulse bg-slate-50" />
    <div v-else-if="!orders.length" class="card p-10 text-center text-slate-500">
      No orders yet. <RouterLink to="/shop" class="text-brand-700">Shop now →</RouterLink>
    </div>
    <div v-else class="space-y-4">
      <div v-for="o in orders" :key="o.id" class="card p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="font-semibold">Order #{{ o.id }}</span>
            <span v-if="o.source === 'draw_win'" class="chip bg-accent-500 text-white">🎉 Draw win</span>
          </div>
          <span class="chip capitalize" :class="statusChip[o.status]">{{ o.status }}</span>
        </div>
        <div class="mt-2 divide-y divide-slate-50 text-sm">
          <div v-for="(it, idx) in o.items" :key="idx" class="flex justify-between py-1">
            <span class="text-slate-600">{{ it.product }} <span class="text-slate-400">× {{ it.qty }}</span></span>
            <span>{{ money(it.line_total) }}</span>
          </div>
        </div>
        <div class="mt-2 flex justify-end gap-6 border-t border-slate-100 pt-2 text-sm">
          <span v-if="o.wallet_applied" class="text-brand-700">Wallet − {{ money(o.wallet_applied) }}</span>
          <span class="font-semibold">Paid {{ money(o.payable) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
