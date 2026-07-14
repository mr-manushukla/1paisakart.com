<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'
import ProductCard from '../components/ProductCard.vue'

const products = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/products')
    products.value = data.data
  } finally {
    loading.value = false
  }
})

const draws = computed(() => products.value.filter((p) => p.allow_draw).slice(0, 4))
const featured = computed(() => products.value.slice(0, 8))
</script>

<template>
  <div class="space-y-12">
    <!-- Hero -->
    <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 to-brand-800 px-6 py-14 text-white sm:px-12">
      <div class="max-w-2xl">
        <span class="chip bg-accent-500 text-white">NEW · The 1% draw</span>
        <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight sm:text-5xl">
          Pay just <span class="text-accent-400">1%</span>. Win the whole thing.
        </h1>
        <p class="mt-4 text-lg text-white/85">
          Join a 100-seat pool for 1% of the price. When it fills, one random winner takes the product —
          everyone else is refunded to their wallet. Or simply buy it outright. Every pool is public.
        </p>
        <div class="mt-6 flex gap-3">
          <RouterLink to="/shop?mode=draw" class="btn-accent">Explore 1% draws</RouterLink>
          <RouterLink to="/shop" class="btn bg-white/10 text-white hover:bg-white/20">Shop all</RouterLink>
        </div>
      </div>
    </section>

    <!-- Trust row -->
    <section class="grid gap-4 sm:grid-cols-3">
      <div class="card flex items-center gap-3 p-4"><span class="text-2xl">🔍</span><div><p class="font-semibold">Transparent pools</p><p class="text-sm text-slate-500">See exactly who's in every draw.</p></div></div>
      <div class="card flex items-center gap-3 p-4"><span class="text-2xl">👛</span><div><p class="font-semibold">Wallet refunds</p><p class="text-sm text-slate-500">Not a winner? Money back to wallet.</p></div></div>
      <div class="card flex items-center gap-3 p-4"><span class="text-2xl">🎲</span><div><p class="font-semibold">Fair random draw</p><p class="text-sm text-slate-500">One winner per 100-seat batch.</p></div></div>
    </section>

    <!-- Live draws -->
    <section v-if="draws.length">
      <div class="mb-4 flex items-end justify-between">
        <h2 class="font-display text-2xl font-bold">Live 1% draws</h2>
        <RouterLink to="/shop?mode=draw" class="text-sm font-medium text-brand-700 hover:underline">View all →</RouterLink>
      </div>
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <ProductCard v-for="p in draws" :key="p.id" :product="p" />
      </div>
    </section>

    <!-- Featured -->
    <section>
      <h2 class="mb-4 font-display text-2xl font-bold">Popular right now</h2>
      <div v-if="loading" class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div v-for="n in 8" :key="n" class="card h-72 animate-pulse bg-slate-50" />
      </div>
      <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <ProductCard v-for="p in featured" :key="p.id" :product="p" />
      </div>
    </section>
  </div>
</template>
