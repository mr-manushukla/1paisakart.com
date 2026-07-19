<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'
import { useCartStore } from '../stores/cart'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'

const cart = useCartStore()
const auth = useAuthStore()
const router = useRouter()

const applyWallet = ref(true)
const placing = ref(false)

// Preview of wallet actually applicable = min(balance, cart cap).
const walletApplied = computed(() => applyWallet.value ? Math.min(auth.walletBalance, cart.walletCap) : 0)
const payable = computed(() => cart.subtotal - walletApplied.value)

async function placeOrder() {
  placing.value = true
  try {
    const { data } = await api.post('/checkout', {
      items: cart.items.map((i) => ({ product_id: i.product_id, qty: i.qty })),
      apply_wallet: applyWallet.value,
    })
    cart.clear()
    await auth.refresh()
    toast(`Order #${data.data.id} placed 🎉`)
    router.push({ name: 'orders' })
  } catch (e) {
    toast(apiError(e), 'error')
  } finally {
    placing.value = false
  }
}
</script>

<template>
  <div>
    <h1 class="mb-6 font-display text-2xl font-bold">Checkout</h1>

    <div v-if="!cart.items.length" class="card p-10 text-center text-slate-500">
      Nothing to check out. <RouterLink to="/shop" class="text-brand-700">Go shopping →</RouterLink>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-[1fr_340px]">
      <div class="card divide-y divide-slate-100 p-2">
        <div v-for="i in cart.items" :key="i.product_id" class="flex items-center justify-between px-3 py-3">
          <span>{{ i.name }} <span class="text-slate-400">× {{ i.qty }}</span></span>
          <span class="font-medium">{{ money(i.listed_price * i.qty) }}</span>
        </div>
      </div>

      <div class="card h-fit p-5">
        <h2 class="font-semibold">Payment</h2>
        <label class="mt-3 flex cursor-pointer items-start gap-2 rounded-lg bg-brand-50 p-3">
          <input v-model="applyWallet" type="checkbox" class="mt-1" />
          <span class="text-sm">
            <span class="font-semibold text-brand-700">Use wallet credit</span>
            <span class="block text-slate-500">Balance {{ money(auth.walletBalance) }} · capped at 1% per item ({{ money(cart.walletCap) }} max here)</span>
          </span>
        </label>

        <div class="mt-4 space-y-1 text-sm">
          <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>{{ money(cart.subtotal) }}</span></div>
          <div class="flex justify-between text-brand-700"><span>Wallet applied</span><span>− {{ money(walletApplied) }}</span></div>
          <div class="mt-2 flex justify-between border-t border-slate-100 pt-2 text-base font-bold"><span>Pay now</span><span>{{ money(payable) }}</span></div>
        </div>

        <button class="btn-primary mt-4 w-full" :disabled="placing" @click="placeOrder">
          {{ placing ? 'Placing…' : `Pay ${money(payable)}` }}
        </button>
        <p class="mt-2 text-center text-xs text-slate-400">Payment gateway is stubbed in this demo.</p>
      </div>
    </div>
  </div>
</template>
