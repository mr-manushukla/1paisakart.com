<script setup>
import { computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useCartStore } from '../stores/cart'
import { useAuthStore } from '../stores/auth'
import { money } from '../lib/money'
import ProductImage from '../components/ProductImage.vue'
import RelatedProducts from '../components/RelatedProducts.vue'

const cart = useCartStore()
const auth = useAuthStore()
const router = useRouter()

// What the wallet would actually take off this cart: 0 when the balance is
// empty, or when there's nothing to buy (advances are real money only).
const walletCovers = computed(() =>
  cart.buyItems.length ? Math.min(auth.walletBalance, cart.walletCap) : 0)

function checkout() {
  router.push({ name: auth.isAuthed && auth.isCustomer ? 'checkout' : 'login', query: auth.isAuthed ? {} : { redirect: '/checkout' } })
}
</script>

<template>
  <div>
    <h1 class="mb-6 font-display text-2xl font-bold">Your cart</h1>

    <div v-if="!cart.items.length" class="card p-10 text-center">
      <p class="text-slate-500">Your cart is empty.</p>
      <RouterLink to="/shop" class="btn-primary mt-4 inline-flex">Start shopping</RouterLink>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-[1fr_320px]">
      <div class="space-y-5">
        <!-- Full-price purchases -->
        <div v-if="cart.buyItems.length" class="space-y-3">
          <div v-for="i in cart.buyItems" :key="i.key" class="card flex items-center gap-4 p-3">
            <div class="h-20 w-20 flex-none"><ProductImage :src="i.image" :name="i.name" /></div>
            <div class="flex-1">
              <RouterLink :to="{ name: 'product', params: { slug: i.slug } }" class="font-semibold hover:text-brand-700">{{ i.name }}</RouterLink>
              <p class="text-sm text-brand-700">{{ money(i.listed_price) }}</p>
            </div>
            <div class="flex items-center gap-2">
              <button class="btn-ghost h-8 w-8 p-0" @click="cart.setQty(i.key, i.qty - 1)">−</button>
              <span class="w-6 text-center">{{ i.qty }}</span>
              <button class="btn-ghost h-8 w-8 p-0" @click="cart.setQty(i.key, i.qty + 1)">+</button>
            </div>
            <p class="w-24 text-right font-semibold">{{ money(i.listed_price * i.qty) }}</p>
            <button class="text-rose-500 hover:text-rose-700" @click="cart.remove(i.key)">✕</button>
          </div>
        </div>

        <!-- 1% advance bookings, saved so they can be completed later -->
        <div v-if="cart.drawItems.length">
          <h2 class="mb-2 text-sm font-semibold text-accent-700">1% advance bookings</h2>
          <div class="space-y-3">
            <div v-for="i in cart.drawItems" :key="i.key" class="flex items-center gap-4 rounded-2xl border-2 border-accent-500/30 bg-accent-500/5 p-3">
              <div class="h-20 w-20 flex-none"><ProductImage :src="i.image" :name="i.name" /></div>
              <div class="flex-1">
                <RouterLink :to="{ name: 'product', params: { slug: i.slug } }" class="font-semibold hover:text-brand-700">{{ i.name }}</RouterLink>
                <p class="text-xs text-slate-500">
                  1% advance on {{ money(i.listed_price) }} · pay the remaining 99% later, or keep the credit
                </p>
              </div>
              <p class="w-24 text-right font-semibold text-accent-700">{{ money(i.entry_price) }}</p>
              <button class="text-rose-500 hover:text-rose-700" @click="cart.remove(i.key)">✕</button>
            </div>
          </div>
        </div>
      </div>

      <div class="card h-fit p-5">
        <h2 class="font-semibold">Summary</h2>
        <div v-if="cart.buyItems.length" class="mt-3 flex justify-between text-sm">
          <span class="text-slate-500">Purchases</span><span>{{ money(cart.subtotal) }}</span>
        </div>
        <div v-if="cart.drawItems.length" class="mt-1 flex justify-between text-sm">
          <span class="text-slate-500">1% advances</span><span class="text-accent-700">{{ money(cart.drawTotal) }}</span>
        </div>
        <!-- Only worth saying when there is credit that can actually be applied here. -->
        <div v-if="walletCovers" class="mt-1 flex justify-between text-sm">
          <span class="text-slate-500">Wallet can cover up to</span><span class="text-brand-700">{{ money(walletCovers) }}</span>
        </div>
        <div class="mt-3 flex justify-between border-t border-slate-100 pt-2 font-bold">
          <span>Total</span><span>{{ money(cart.subtotal + cart.drawTotal) }}</span>
        </div>
        <button class="btn-primary mt-4 w-full" @click="checkout">Checkout</button>
        <RouterLink to="/shop" class="mt-2 block text-center text-sm text-slate-500 hover:text-brand-700">Continue shopping</RouterLink>
      </div>
    </div>

    <div v-if="cart.items.length" class="mt-12">
      <RelatedProducts :slug="cart.items[0].slug" title="Frequently bought with these" />
    </div>
  </div>
</template>
