<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '../lib/api'
import ProductCard from '../components/ProductCard.vue'

const route = useRoute()
const router = useRouter()
const products = ref([])
const meta = ref({ current_page: 1, last_page: 1 })
const categories = ref([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/products', {
      params: {
        q: route.query.q || undefined,
        category: route.query.category || undefined,
        mode: route.query.mode || undefined,
        page: route.query.page || 1,
      },
    })
    products.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

function setQuery(patch) {
  router.push({ name: 'shop', query: { ...route.query, page: undefined, ...patch } })
}

onMounted(async () => {
  const { data } = await api.get('/categories')
  categories.value = data
  load()
})
watch(() => route.query, load)
</script>

<template>
  <div class="grid gap-6 md:grid-cols-[220px_1fr]">
    <!-- Filters -->
    <aside class="space-y-6">
      <div class="card p-4">
        <h3 class="mb-3 font-semibold">Buying mode</h3>
        <div class="flex flex-col gap-1 text-sm">
          <button class="rounded-lg px-3 py-1.5 text-left" :class="!route.query.mode ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'" @click="setQuery({ mode: undefined })">All products</button>
          <button class="rounded-lg px-3 py-1.5 text-left" :class="route.query.mode === 'draw' ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'" @click="setQuery({ mode: 'draw' })">1% Draws</button>
          <button class="rounded-lg px-3 py-1.5 text-left" :class="route.query.mode === 'buy' ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'" @click="setQuery({ mode: 'buy' })">Buy now</button>
        </div>
      </div>
      <div class="card p-4">
        <h3 class="mb-3 font-semibold">Categories</h3>
        <div class="flex flex-col gap-1 text-sm">
          <button class="rounded-lg px-3 py-1.5 text-left" :class="!route.query.category ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'" @click="setQuery({ category: undefined })">All categories</button>
          <button v-for="c in categories" :key="c.id" class="flex justify-between rounded-lg px-3 py-1.5 text-left" :class="route.query.category === c.slug ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'" @click="setQuery({ category: c.slug })">
            <span>{{ c.name }}</span><span class="text-slate-400">{{ c.products_count }}</span>
          </button>
        </div>
      </div>
    </aside>

    <!-- Results -->
    <section>
      <h1 class="mb-4 font-display text-2xl font-bold">
        {{ route.query.mode === 'draw' ? '1% Draws' : route.query.q ? `Results for “${route.query.q}”` : 'All products' }}
      </h1>

      <div v-if="loading" class="grid grid-cols-2 gap-4 lg:grid-cols-3">
        <div v-for="n in 6" :key="n" class="card h-72 animate-pulse bg-slate-50" />
      </div>
      <div v-else-if="!products.length" class="card p-10 text-center text-slate-500">No products found.</div>
      <div v-else class="grid grid-cols-2 gap-4 lg:grid-cols-3">
        <ProductCard v-for="p in products" :key="p.id" :product="p" />
      </div>

      <div v-if="meta.last_page > 1" class="mt-6 flex items-center justify-center gap-2">
        <button class="btn-ghost" :disabled="meta.current_page <= 1" @click="setQuery({ page: meta.current_page - 1 })">Prev</button>
        <span class="text-sm text-slate-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button class="btn-ghost" :disabled="meta.current_page >= meta.last_page" @click="setQuery({ page: meta.current_page + 1 })">Next</button>
      </div>
    </section>
  </div>
</template>
