<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import { toast, apiError } from '../lib/toast'
import ImageGallery from '../components/ImageGallery.vue'
import DrawProgress from '../components/DrawProgress.vue'
import StarRating from '../components/StarRating.vue'
import QuantityStepper from '../components/QuantityStepper.vue'
import WishlistHeart from '../components/WishlistHeart.vue'
import ShareButtons from '../components/ShareButtons.vue'
import ProductSpecs from '../components/ProductSpecs.vue'
import ProductReviews from '../components/ProductReviews.vue'
import RelatedProducts from '../components/RelatedProducts.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const cart = useCartStore()

const product = ref(null)
const batch = ref(null)
const loading = ref(true)
const joining = ref(false)
const qty = ref(1)
let poll

const slug = route.params.slug
const hasOpenPool = computed(() => batch.value && batch.value.open !== false)

async function fetchProduct() {
  const { data } = await api.get(`/products/${slug}`)
  product.value = data.data
}
async function fetchBatch() {
  const { data } = await api.get(`/products/${slug}/batch`)
  batch.value = data.data ?? data
}

onMounted(async () => {
  try {
    await Promise.all([fetchProduct(), fetchBatch()])
  } finally {
    loading.value = false
  }
  poll = setInterval(fetchBatch, 8000)
})
onUnmounted(() => clearInterval(poll))

function addToCart() {
  cart.add(product.value, qty.value)
  toast(`${qty.value} × ${product.value.name} added to cart`)
}
function buyNow() {
  cart.add(product.value, qty.value)
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
      <!-- Gallery -->
      <div class="card p-4">
        <ImageGallery :images="product.images" :name="product.name" />
      </div>

      <!-- Info + CTAs -->
      <div>
        <div class="flex items-start justify-between gap-3">
          <div>
            <RouterLink v-if="product.category" :to="{ name: 'shop', query: { category: product.category.slug } }" class="text-sm font-medium uppercase tracking-wide text-brand-600">
              {{ product.category.name }}
            </RouterLink>
            <h1 class="mt-1 font-display text-3xl font-bold">{{ product.name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
              <span v-if="product.brand" class="font-medium text-slate-600">{{ product.brand }}</span>
              <span v-if="product.brand"> · </span>Sold by {{ product.shop?.name }}
            </p>
          </div>
          <WishlistHeart :product="product" />
        </div>

        <div v-if="product.reviews_count" class="mt-2 flex items-center gap-1.5">
          <StarRating :value="product.rating" />
          <span class="text-sm text-slate-500">{{ product.rating }} · {{ product.reviews_count }} reviews</span>
        </div>
        <p class="mt-4 font-display text-3xl font-extrabold text-brand-700">{{ money(product.listed_price) }}</p>

        <!-- 100% buy -->
        <div v-if="product.allow_full_buy" class="card mt-6 p-4">
          <p class="text-sm font-semibold text-slate-700">Buy it outright</p>
          <p class="text-xs text-slate-500">In stock: {{ product.stock }} · Wallet covers up to {{ money(product.max_wallet_applicable) }} (1%).</p>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <QuantityStepper v-model="qty" :max="Math.max(1, product.stock)" />
            <button class="btn-primary flex-1" :disabled="product.stock < 1" @click="buyNow">Buy now · {{ money(product.listed_price * qty) }}</button>
            <button class="btn-ghost" :disabled="product.stock < 1" @click="addToCart">Add to cart</button>
          </div>
        </div>

        <!-- 1% draw -->
        <div v-if="product.draw_eligible" class="mt-4 rounded-2xl border-2 border-accent-500/30 bg-accent-500/5 p-4">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-accent-700">🎲 Book with a 1% advance</p>
            <span class="chip bg-accent-500 text-white">{{ money(product.entry_price) }} now</span>
          </div>
          <p v-if="product.open_batch?.club" class="mt-1 text-xs font-medium text-slate-600">
            Club {{ product.open_batch.club.label }} · odds 1 in {{ product.open_batch.size }}
          </p>
          <p class="mt-1 text-xs text-slate-500">
            <strong>Win</strong> and the product is yours — your 1% covers it (government taxes on the prize apply).
            <strong>Didn't win?</strong> Either pay the remaining {{ money(product.listed_price - product.entry_price) }} to buy it,
            or move your {{ money(product.entry_price) }} to your wallet.
          </p>
          <div v-if="hasOpenPool" class="mt-3">
            <DrawProgress :filled="batch.filled" :size="batch.size" :entry-price="product.entry_price" />
          </div>
          <p v-else class="mt-3 text-sm text-slate-500">No open pool yet — <span class="font-semibold text-accent-700">be the first to book a seat!</span></p>
          <button class="btn-accent mt-3 w-full" :disabled="joining" @click="joinDraw">
            {{ joining ? 'Booking…' : `Book for ${money(product.entry_price)}` }}
          </button>
        </div>

        <!-- Share -->
        <div class="mt-4 border-t border-slate-100 pt-3">
          <ShareButtons :title="product.name" />
        </div>
      </div>
    </div>

    <!-- Description + product information -->
    <ProductSpecs :description="product.description" :brand="product.brand" :specs="product.specs" />

    <!-- Transparency: who's in the pool -->
    <section v-if="product.draw_eligible && hasOpenPool" class="card p-6">
      <div class="mb-4 flex items-center justify-between">
        <div>
          <h2 class="font-display text-xl font-bold">Who's in the pool <span class="text-slate-400">· {{ batch.club?.label }} · pool #{{ batch.batch_no }}</span></h2>
          <p class="text-sm text-slate-500">100% transparent — every seat is public, including which product each member booked. Updates live.</p>
        </div>
        <span class="chip bg-brand-50 text-brand-700">{{ batch.filled }}/{{ batch.size }} seats</span>
      </div>
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
        <div v-for="p in batch.participants" :key="p.seat" class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50/60 px-2.5 py-1.5 text-sm">
          <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ p.seat }}</span>
          <span class="min-w-0">
            <span class="block truncate text-slate-600">{{ p.name }}</span>
            <span class="block truncate text-[11px] text-slate-400">{{ p.product }}</span>
          </span>
        </div>
        <div v-for="n in (batch.size - batch.filled)" :key="'e' + n" class="flex items-center gap-2 rounded-lg border border-dashed border-slate-200 px-2.5 py-1.5 text-sm text-slate-300">
          <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-slate-100 text-xs">·</span>
          <span>open seat</span>
        </div>
      </div>
    </section>

    <!-- Reviews -->
    <ProductReviews :slug="slug" />

    <!-- Cross-sell -->
    <RelatedProducts :slug="slug" />
  </div>
</template>
