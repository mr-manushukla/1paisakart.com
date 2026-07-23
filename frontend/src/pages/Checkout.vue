<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'
import { useCartStore } from '../stores/cart'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'
import { payAndFulfil } from '../lib/razorpay'

const cart = useCartStore()
const auth = useAuthStore()
const router = useRouter()

const applyWallet = ref(true)
const placing = ref(false)

// Coupons
const couponCode = ref('')
const coupon = ref(null)      // { code, discount, scope }
const couponBusy = ref(false)

const discount = computed(() => coupon.value?.discount ?? 0)
const afterDiscount = computed(() => Math.max(0, cart.subtotal - discount.value))
// Wallet may only reduce the purchase portion — never a 1% advance.
const walletCovers = computed(() =>
  cart.buyItems.length ? Math.min(auth.walletBalance, cart.walletCap, afterDiscount.value) : 0)
const walletApplied = computed(() => (applyWallet.value ? walletCovers.value : 0))
const payable = computed(() => afterDiscount.value - walletApplied.value + cart.drawTotal)

async function applyCoupon() {
  if (!couponCode.value.trim()) return
  couponBusy.value = true
  try {
    const { data } = await api.post('/coupons/validate', {
      code: couponCode.value.trim(),
      items: cart.buyItems.map((i) => ({ product_id: i.product_id, qty: i.qty })),
    })
    coupon.value = data
    toast(`Coupon ${data.code} applied — you save ${money(data.discount)}`)
  } catch (e) {
    coupon.value = null
    toast(apiError(e, 'That coupon could not be applied'), 'error')
  } finally {
    couponBusy.value = false
  }
}
function removeCoupon() { coupon.value = null; couponCode.value = '' }

async function placeOrder() {
  placing.value = true
  try {
    const items = cart.buyItems.map((i) => ({ product_id: i.product_id, qty: i.qty }))
    const drawItems = cart.drawItems.map((i) => i.product_id)

    // Wallet covered the whole purchase and there's nothing to book → no gateway needed.
    if (payable.value <= 0 && !drawItems.length) {
      const { data } = await api.post('/checkout', { items, apply_wallet: applyWallet.value, coupon_code: coupon.value?.code ?? null })
      cart.clear()
      await auth.refresh()
      toast(`Order #${data.data.id} placed 🎉`)
      return router.push({ name: 'orders' })
    }

    const result = await payAndFulfil({
      intent: 'checkout',
      items,
      draw_items: drawItems,
      apply_wallet: applyWallet.value,
      coupon_code: coupon.value?.code ?? null,
    })
    // User closed checkout — keep the cart so they can finish later.
    if (!result) return toast('Payment cancelled — your cart has been kept', 'error')

    cart.clear()
    await auth.refresh()

    if (result.result?.refunded_to_wallet?.length || result.refunded_to_wallet?.length) {
      const names = (result.result?.refunded_to_wallet || result.refunded_to_wallet).join(', ')
      toast(`Paid. Couldn't book: ${names} — that advance is back in your wallet.`, 'error')
    } else {
      toast('Payment successful 🎉')
    }
    router.push({ name: 'orders' })
  } catch (e) {
    toast(apiError(e, e?.message || 'Payment failed'), 'error')
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
      <div class="space-y-4">
        <div v-if="cart.buyItems.length" class="card divide-y divide-slate-100 p-2">
          <div v-for="i in cart.buyItems" :key="i.key" class="flex items-center justify-between px-3 py-3">
            <span>{{ i.name }} <span class="text-slate-400">× {{ i.qty }}</span></span>
            <span class="font-medium">{{ money(i.listed_price * i.qty) }}</span>
          </div>
        </div>

        <div v-if="cart.drawItems.length" class="rounded-2xl border-2 border-accent-500/30 bg-accent-500/5 p-2">
          <p class="px-3 pt-2 text-sm font-semibold text-accent-700">1% advance bookings</p>
          <div v-for="i in cart.drawItems" :key="i.key" class="flex items-center justify-between px-3 py-2.5 last:pb-3">
            <span class="text-sm">{{ i.name }}</span>
            <span class="font-medium">{{ money(i.entry_price) }}</span>
          </div>
        </div>
      </div>

      <div class="card h-fit p-5">
        <h2 class="font-semibold">Payment</h2>
        <!-- Hidden outright when there's no credit to apply — an empty wallet
             offer is just noise, and the checkbox would do nothing. -->
        <label v-if="walletCovers" class="mt-3 flex cursor-pointer items-start gap-2 rounded-lg bg-brand-50 p-3">
          <input v-model="applyWallet" type="checkbox" class="mt-1" />
          <span class="text-sm">
            <span class="font-semibold text-brand-700">Use wallet credit</span>
            <span class="block text-slate-500">Balance {{ money(auth.walletBalance) }} · capped at 1% per item ({{ money(cart.walletCap) }} max here)</span>
          </span>
        </label>

        <!-- Coupon -->
        <div v-if="cart.buyItems.length" class="mt-3">
          <div v-if="coupon" class="flex items-center justify-between rounded-lg bg-green-50 px-3 py-2">
            <span class="text-sm">
              <span class="font-semibold text-green-800">{{ coupon.code }}</span>
              <span class="block text-xs text-green-700">You save {{ money(coupon.discount) }} · {{ coupon.scope }}</span>
            </span>
            <button class="text-xs text-slate-500 hover:text-rose-600" @click="removeCoupon">Remove</button>
          </div>
          <form v-else class="flex gap-2" @submit.prevent="applyCoupon">
            <input v-model="couponCode" class="input text-sm uppercase" placeholder="Coupon code" />
            <button class="btn-ghost text-sm" :disabled="couponBusy">{{ couponBusy ? '…' : 'Apply' }}</button>
          </form>
        </div>

        <div class="mt-4 space-y-1 text-sm">
          <div v-if="cart.buyItems.length" class="flex justify-between"><span class="text-slate-500">Purchases</span><span>{{ money(cart.subtotal) }}</span></div>
          <div v-if="discount" class="flex justify-between text-green-700"><span>Coupon {{ coupon.code }}</span><span>− {{ money(discount) }}</span></div>
          <div v-if="cart.drawItems.length" class="flex justify-between"><span class="text-slate-500">1% advances</span><span>{{ money(cart.drawTotal) }}</span></div>
          <div v-if="walletApplied" class="flex justify-between text-brand-700"><span>Wallet applied</span><span>− {{ money(walletApplied) }}</span></div>
          <div class="mt-2 flex justify-between border-t border-slate-100 pt-2 text-base font-bold"><span>Pay now</span><span>{{ money(payable) }}</span></div>
        </div>

        <button class="btn-primary mt-4 w-full" :disabled="placing" @click="placeOrder">
          {{ placing ? 'Processing…' : `Pay ${money(payable)}` }}
        </button>
        <p class="mt-2 text-center text-xs text-slate-400">Secure payment via Razorpay.</p>
      </div>
    </div>
  </div>
</template>
