<script setup>
import { RouterLink, useRouter } from 'vue-router'
import { useCartStore } from '../stores/cart'
import { useAuthStore } from '../stores/auth'
import { money } from '../lib/money'
import ProductImage from '../components/ProductImage.vue'
import RelatedProducts from '../components/RelatedProducts.vue'

const cart = useCartStore()
const auth = useAuthStore()
const router = useRouter()

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
      <div class="space-y-3">
        <div v-for="i in cart.items" :key="i.product_id" class="card flex items-center gap-4 p-3">
          <div class="h-20 w-20 flex-none"><ProductImage :src="i.image" :name="i.name" /></div>
          <div class="flex-1">
            <RouterLink :to="{ name: 'product', params: { slug: i.slug } }" class="font-semibold hover:text-brand-700">{{ i.name }}</RouterLink>
            <p class="text-sm text-brand-700">{{ money(i.listed_price) }}</p>
          </div>
          <div class="flex items-center gap-2">
            <button class="btn-ghost h-8 w-8 p-0" @click="cart.setQty(i.product_id, i.qty - 1)">−</button>
            <span class="w-6 text-center">{{ i.qty }}</span>
            <button class="btn-ghost h-8 w-8 p-0" @click="cart.setQty(i.product_id, i.qty + 1)">+</button>
          </div>
          <p class="w-24 text-right font-semibold">{{ money(i.listed_price * i.qty) }}</p>
          <button class="text-rose-500 hover:text-rose-700" @click="cart.remove(i.product_id)">✕</button>
        </div>
      </div>

      <div class="card h-fit p-5">
        <h2 class="font-semibold">Summary</h2>
        <div class="mt-3 flex justify-between text-sm"><span class="text-slate-500">Subtotal</span><span>{{ money(cart.subtotal) }}</span></div>
        <div class="mt-1 flex justify-between text-sm"><span class="text-slate-500">Wallet can cover up to</span><span class="text-brand-700">{{ money(cart.walletCap) }}</span></div>
        <button class="btn-primary mt-4 w-full" @click="checkout">Checkout</button>
        <RouterLink to="/shop" class="mt-2 block text-center text-sm text-slate-500 hover:text-brand-700">Continue shopping</RouterLink>
      </div>
    </div>

    <div v-if="cart.items.length" class="mt-12">
      <RelatedProducts :slug="cart.items[0].slug" title="Frequently bought with these" />
    </div>
  </div>
</template>
