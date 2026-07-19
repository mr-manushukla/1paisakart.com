<script setup>
import { ref, onMounted } from 'vue'
import api from '../lib/api'
import { money } from '../lib/money'

const balance = ref(0)
const txns = ref([])
const loading = ref(true)

const labels = { draw_refund: 'Draw refund', purchase_debit: 'Used on order', admin_adjust: 'Adjustment' }

onMounted(async () => {
  try {
    const { data } = await api.get('/wallet')
    balance.value = data.balance
    txns.value = data.transactions.data ?? data.transactions
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <h1 class="mb-6 font-display text-2xl font-bold">My wallet</h1>

    <div class="card bg-gradient-to-br from-brand-600 to-brand-800 p-6 text-white">
      <p class="text-sm text-white/80">Wallet balance</p>
      <p class="font-display text-4xl font-extrabold">{{ money(balance) }}</p>
      <p class="mt-2 text-xs text-white/80">Restricted credit — usable on any product you buy outright, up to 1% of that item's price. Cannot be used to pay a 1% booking advance.</p>
    </div>

    <h2 class="mb-2 mt-8 font-semibold">Transaction history</h2>
    <div v-if="loading" class="card h-40 animate-pulse bg-slate-50" />
    <div v-else-if="!txns.length" class="card p-8 text-center text-slate-500">No transactions yet.</div>
    <div v-else class="card divide-y divide-slate-100">
      <div v-for="t in txns" :key="t.id" class="flex items-center justify-between px-4 py-3">
        <div>
          <p class="font-medium">{{ labels[t.type] || t.type }}</p>
          <p class="text-xs text-slate-500">{{ t.note }}</p>
        </div>
        <div class="text-right">
          <p class="font-semibold" :class="t.amount >= 0 ? 'text-brand-700' : 'text-rose-600'">
            {{ t.amount >= 0 ? '+' : '−' }} {{ money(Math.abs(t.amount)) }}
          </p>
          <p class="text-xs text-slate-400">Bal {{ money(t.balance_after) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
