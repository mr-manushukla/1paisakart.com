<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import { toast, apiError } from '../lib/toast'
import ProductImage from '../components/ProductImage.vue'
import DrawProgress from '../components/DrawProgress.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const cart = useCartStore()

const product = ref(null)
const batch = ref(null)          // { open:false } or a batch object
const loading = ref(true)
const joining = ref(false)
let poll

const slug = route.params.slug
const hasOpenPool = computed(() => batch.value && batch.value.open !== false)

async function fetchProduct() {
  const { data } = await api.get(`/products/${slug}`)
  product.value = data.data
}
async function fetchBatch() {
  const { data } = await api.get(`/products/${slug}/batch`)
  batch.value = data.data ?? data // data-wrapped when a real batch, plain {open:false} otherwise
}

onMounted(async () => {
  try {
    await Promise.all([fetchProduct(), fetchBatch()])
  } finally {
    loading.value = false
  }
  poll = setInterval(fetchBatch, 8000) // live pool
})
onUnmounted(() => clearInterval(poll))

function addToCart() {
  cart.add(product.value)
  toast(`${product.value.name} added to cart`)
}
function buyNow() {
  cart.add(product.value)
  router.push({ name: auth.isAuthed && auth.isCustomer ? 'checkout' : 'cart' })
}
async function joinDraw() {
  if (!auth.isAuthed) return router.push({ name: 'login', query: { redirect: route.fullPath } })
  if (!auth.isCustomer) return toast('Only customer accounts can join draws.', 'error')
  joining.value = true
  try {
    const { data } = await api.post(`/products/${slug}/enter-draw`)
    toast(data.message)
    await Promise.all([fetchBatch(), fetchProduct(), auth.refresh()])
  } catch (e) {
    toast(apiError(e), 'error')
  } finally {
    joining.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="grid gap-8 md:grid-cols-2">
    <div class="card h-96 animate-pulse bg-slate-50" />
    <div class="card h-96 animate-pulse bg-slate-50" />
  </div>

  <div v-else-if="product" class="space-y-8">
    <div class="grid gap-8 md:grid-cols-2">
      <!-- Image -->
      <div class="card p-4">
        <ProductImage :src="product.image" :name="product.name" />
      </div>

      <!-- Info + CTAs -->
      <div>
        <RouterLink v-if="product.category" :to="{ name: 'shop', query: { category: product.category.slug } }" class="text-sm font-medium uppercase tracking-wide text-brand-600">
          {{ product.category.name }}
        </RouterLink>
        <h1 class="mt-1 font-display text-3xl font-bold">{{ product.name }}</h1>
        <p class="mt-1 text-sm text-slate-500">Sold by {{ product.shop?.name }}</p>
        <p class="mt-4 font-display text-3xl font-extrabold text-brand-700">{{ money(product.listed_price) }}</p>
        <p class="mt-4 text-slate-600">{{ product.description }}</p>

        <!-- 100% buy -->
        <div v-if="product.allow_full_buy" class="card mt-6 p-4">
          <p class="text-sm font-semibold text-slate-700">Buy it outright</p>
          <p class="text-xs text-slate-500">In stock: {{ product.stock }} · Wallet covers up to {{ money(product.max_wallet_applicable) }} (10%).</p>
          <div class="mt-3 flex gap-2">
            <button class="btn-primary flex-1" :disabled="product.stock < 1" @click="buyNow">Buy now · {{ money(product.listed_price) }}</button>
            <button class="btn-ghost" :disabled="product.stock < 1" @click="addToCart">Add to cart</button>
          </div>
        </div>

        <!-- 1% draw -->
        <div v-if="product.allow_draw" class="mt-4 rounded-2xl border-2 border-accent-500/30 bg-accent-500/5 p-4">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-accent-700">🎲 Join the 1% draw</p>
            <span class="chip bg-accent-500 text-white">Win for {{ money(product.entry_price) }}</span>
          </div>
          <p class="mt-1 text-xs text-slate-500">Pay 1% to grab a seat in a 100-person pool. Pool fills → one random winner keeps it. Not you? Full refund to wallet.</p>
          <div v-if="hasOpenPool" class="mt-3">
            <DrawProgress :filled="batch.filled" :size="batch.size" :entry-price="batch.entry_price" />
          </div>
          <p v-else class="mt-3 text-sm text-slate-500">No open pool yet — <span class="font-semibold text-accent-700">be the first to start one!</span></p>
          <button class="btn-accent mt-3 w-full" :disabled="joining" @click="joinDraw">
            {{ joining ? 'Joining…' : `Join draw for ${money(product.entry_price)}` }}
          </button>
        </div>
      </div>
    </div>

    <!-- Transparency: who's in the pool -->
    <section v-if="product.allow_draw && hasOpenPool" class="card p-6">
      <div class="mb-4 flex items-center justify-between">
        <div>
          <h2 class="font-display text-xl font-bold">Who's in the pool <span class="text-slate-400">· batch #{{ batch.batch_no }}</span></h2>
          <p class="text-sm text-slate-500">100% transparent — every seat is public. Updates live.</p>
        </div>
        <span class="chip bg-brand-50 text-brand-700">{{ batch.filled }}/{{ batch.size }} seats</span>
      </div>
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
        <div v-for="p in batch.participants" :key="p.seat" class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50/60 px-2.5 py-1.5 text-sm">
          <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ p.seat }}</span>
          <span class="truncate text-slate-600">{{ p.name }}</span>
        </div>
        <div v-for="n in (batch.size - batch.filled)" :key="'e' + n" class="flex items-center gap-2 rounded-lg border border-dashed border-slate-200 px-2.5 py-1.5 text-sm text-slate-300">
          <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-slate-100 text-xs">·</span>
          <span>open seat</span>
        </div>
      </div>
    </section>
  </div>
</template>
