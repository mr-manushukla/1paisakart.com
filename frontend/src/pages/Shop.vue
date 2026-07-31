<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '../lib/api'
import ProductCard from '../components/ProductCard.vue'
import PriceRangeFilter from '../components/PriceRangeFilter.vue'
import PoolRangeBar from '../components/PoolRangeBar.vue'
import { useDeliveryStore } from '../stores/delivery'

const route = useRoute()
const router = useRouter()
const delivery = useDeliveryStore()
const products = ref([])
const meta = ref({ current_page: 1, last_page: 1 })
const categories = ref([])
const loading = ref(true)

// Price is handled in whole rupees in the UI and paise on the wire.
const bounds = ref({ min: 0, max: 100000 })
const price = ref({ from: 0, to: 100000 })

async function loadBounds() {
  const { data } = await api.get('/price-range', { params: delivery.params() })
  bounds.value = { min: Math.floor(data.min / 100), max: Math.ceil(data.max / 100) }
  price.value = {
    from: route.query.min_price ? Math.floor(route.query.min_price / 100) : bounds.value.min,
    to: route.query.max_price ? Math.ceil(route.query.max_price / 100) : bounds.value.max,
  }
}

/** Push the slider values into the URL so filters survive reload and sharing. */
function applyPrice() {
  const atMin = price.value.from <= bounds.value.min
  const atMax = price.value.to >= bounds.value.max
  setQuery({
    min_price: atMin ? undefined : price.value.from * 100,
    max_price: atMax ? undefined : price.value.to * 100,
  })
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/products', {
      params: {
        q: route.query.q || undefined,
        category: route.query.category || undefined,
        mode: route.query.mode || undefined,
        min_price: route.query.min_price || undefined,
        max_price: route.query.max_price || undefined,
        page: route.query.page || 1,
        ...delivery.params(),
      },
    })
    products.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

// On mobile the filter panel is collapsed so products own the screen.
const filtersOpen = ref(false)
const activeFilterCount = computed(() =>
  ['mode', 'category', 'min_price', 'max_price'].filter((k) => route.query[k]).length)

/** Tapping a pool band just sets the price window the band covers. */
function selectPool(club) {
  setQuery(club
    ? { min_price: club.min_price, max_price: club.max_price }
    : { min_price: undefined, max_price: undefined })
}

function setQuery(patch) {
  router.push({ name: 'shop', query: { ...route.query, page: undefined, ...patch } })
}

onMounted(async () => {
  const { data } = await api.get('/categories')
  categories.value = data
  await loadBounds()
  load()
})
watch(() => route.query, load)
// A new delivery PIN changes both which products exist and their price bounds.
watch(() => delivery.pincode, async () => { await loadBounds(); load() })
</script>

<template>
  <div>
    <!-- Pool bands first: the primary way to browse, one tap, no panel.
         Extra filters sit beside it rather than above the products. -->
    <div class="mb-4 flex items-center gap-2">
      <div class="min-w-0 flex-1">
        <PoolRangeBar :min="route.query.min_price" :max="route.query.max_price" @select="selectPool" />
      </div>
      <button
        class="btn-ghost shrink-0 px-3 py-1.5 text-sm md:hidden"
        :class="{ 'border-brand-600 text-brand-700': filtersOpen || activeFilterCount }"
        @click="filtersOpen = !filtersOpen"
      >
        Filters<span v-if="activeFilterCount" class="ml-1 font-bold">{{ activeFilterCount }}</span>
      </button>
    </div>

  <div class="grid gap-6 md:grid-cols-[220px_1fr]">
    <!-- Filters: a sidebar on desktop, collapsed behind the button on mobile -->
    <aside class="space-y-6" :class="filtersOpen ? '' : 'hidden md:block'">
      <div class="card p-4">
        <h3 class="mb-3 font-semibold">Buying mode</h3>
        <div class="flex flex-col gap-1 text-sm">
          <button class="rounded-lg px-3 py-1.5 text-left" :class="!route.query.mode ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'" @click="setQuery({ mode: undefined })">All products</button>
          <button class="rounded-lg px-3 py-1.5 text-left" :class="route.query.mode === 'draw' ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'" @click="setQuery({ mode: 'draw' })">1% Draws</button>
          <button class="rounded-lg px-3 py-1.5 text-left" :class="route.query.mode === 'buy' ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-50'" @click="setQuery({ mode: 'buy' })">Buy now</button>
        </div>
      </div>
      <div class="card p-4">
        <PriceRangeFilter v-model="price" :min="bounds.min" :max="bounds.max" @apply="applyPrice" />
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
      <div v-else-if="!products.length" class="card p-10 text-center text-slate-500">
        No products found.
        <span v-if="delivery.active" class="mt-1 block text-sm">
          Nothing here delivers to <strong>{{ delivery.pincode }}</strong> — try another PIN code.
        </span>
      </div>
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
  </div>
</template>
