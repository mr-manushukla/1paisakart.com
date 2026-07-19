<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'
import HeroSlider from '../components/HeroSlider.vue'
import CategoryTiles from '../components/CategoryTiles.vue'
import ProductCard from '../components/ProductCard.vue'

const products = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    products.value = (await api.get('/products')).data.data
  } finally {
    loading.value = false
  }
})

const draws = computed(() => products.value.filter((p) => p.draw_eligible).slice(0, 4))
const bestSellers = computed(() =>
  [...products.value].sort((a, b) => (b.reviews_count || 0) - (a.reviews_count || 0)).slice(0, 4)
)
const featured = computed(() => products.value.slice(0, 8))

const testimonials = [
  { name: 'Aarti S.', place: 'Delhi', text: 'Won a smartwatch for ₹50 in a draw. When I didn’t win another pool, the refund hit my wallet instantly.' },
  { name: 'Rahul M.', place: 'Pune', text: 'I love that I can see everyone in the pool before joining. Feels genuinely fair and transparent.' },
  { name: 'Neha K.', place: 'Bengaluru', text: 'Buy-now prices are solid and the wallet credit for the next order is a nice touch.' },
]
</script>

<template>
  <div class="space-y-14">
    <HeroSlider />

    <!-- Trust bar -->
    <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <div class="card flex items-center gap-2 p-3 text-sm"><span class="text-xl">🚚</span> Fast delivery</div>
      <div class="card flex items-center gap-2 p-3 text-sm"><span class="text-xl">👛</span> Wallet refunds</div>
      <div class="card flex items-center gap-2 p-3 text-sm"><span class="text-xl">🎲</span> Provably fair draw</div>
      <div class="card flex items-center gap-2 p-3 text-sm"><span class="text-xl">🔒</span> Secure checkout</div>
    </section>

    <CategoryTiles />

    <!-- Live draws -->
    <section v-if="draws.length">
      <div class="mb-4 flex items-end justify-between">
        <div>
          <h2 class="font-display text-2xl font-bold">Live 1% draws</h2>
          <p class="text-sm text-slate-500">Book for 1% · odds 1 in 100 · didn't win? buy it or keep the credit.</p>
        </div>
        <RouterLink to="/shop?mode=draw" class="text-sm font-medium text-brand-700 hover:underline">View all →</RouterLink>
      </div>
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <ProductCard v-for="p in draws" :key="p.id" :product="p" />
      </div>
    </section>

    <!-- How the draw works (trust / transparency explainer) -->
    <section class="rounded-3xl bg-brand-50 p-8">
      <h2 class="text-center font-display text-2xl font-bold">How the 1% draw works</h2>
      <div class="mt-6 grid gap-6 md:grid-cols-3">
        <div class="text-center">
          <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 font-bold text-white">1</div>
          <p class="mt-2 font-semibold">Book with a 1% advance</p>
          <p class="text-sm text-slate-500">Pay 1% to take one of 100 seats in your product's price-band club.</p>
        </div>
        <div class="text-center">
          <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 font-bold text-white">2</div>
          <p class="mt-2 font-semibold">The pool fills to 100</p>
          <p class="text-sm text-slate-500">Watch it live — every seat is public, including what each member booked. Odds 1 in 100.</p>
        </div>
        <div class="text-center">
          <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 font-bold text-white">3</div>
          <p class="mt-2 font-semibold">Win it — or choose</p>
          <p class="text-sm text-slate-500">The winner keeps their product for the 1%. Everyone else pays the remaining 99% to buy it, or moves the 1% to their wallet.</p>
        </div>
      </div>
    </section>

    <!-- Best sellers -->
    <section v-if="bestSellers.length">
      <h2 class="mb-4 font-display text-2xl font-bold">Best sellers</h2>
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <ProductCard v-for="p in bestSellers" :key="p.id" :product="p" />
      </div>
    </section>

    <!-- Featured grid -->
    <section>
      <h2 class="mb-4 font-display text-2xl font-bold">Popular right now</h2>
      <div v-if="loading" class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div v-for="n in 8" :key="n" class="card h-72 animate-pulse bg-slate-50" />
      </div>
      <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <ProductCard v-for="p in featured" :key="p.id" :product="p" />
      </div>
    </section>

    <!-- Testimonials -->
    <section>
      <h2 class="mb-4 text-center font-display text-2xl font-bold">Loved by shoppers</h2>
      <div class="grid gap-4 md:grid-cols-3">
        <div v-for="t in testimonials" :key="t.name" class="card p-5">
          <div class="text-accent-500">★★★★★</div>
          <p class="mt-2 text-sm text-slate-600">“{{ t.text }}”</p>
          <p class="mt-3 text-sm font-semibold">{{ t.name }} <span class="font-normal text-slate-400">· {{ t.place }}</span></p>
        </div>
      </div>
    </section>

    <!-- Newsletter -->
    <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 to-brand-900 px-6 py-10 text-center text-white">
      <h2 class="font-display text-2xl font-bold">Never miss a draw</h2>
      <p class="mx-auto mt-2 max-w-lg text-white/85">Get notified when new 1% pools open. No spam — just the good stuff.</p>
      <form class="mx-auto mt-5 flex max-w-md gap-2" @submit.prevent>
        <input class="input flex-1 border-0 text-slate-800" placeholder="you@email.com" />
        <button class="btn-accent">Subscribe</button>
      </form>
    </section>
  </div>
</template>
