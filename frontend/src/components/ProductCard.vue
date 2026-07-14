<script setup>
import { RouterLink } from 'vue-router'
import ProductImage from './ProductImage.vue'
import DrawProgress from './DrawProgress.vue'
import StarRating from './StarRating.vue'
import { money } from '../lib/money'
import { useCartStore } from '../stores/cart'
import { toast } from '../lib/toast'

const props = defineProps({ product: { type: Object, required: true } })
const cart = useCartStore()

function addToCart() {
  cart.add(props.product)
  toast(`${props.product.name} added to cart`)
}
</script>

<template>
  <div class="card flex flex-col overflow-hidden p-3 transition hover:shadow-md">
    <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="relative block">
      <ProductImage :src="product.image" :name="product.name" />
      <span v-if="product.allow_draw" class="chip absolute left-2 top-2 bg-accent-500 text-white">1% DRAW</span>
    </RouterLink>

    <div class="mt-3 flex flex-1 flex-col">
      <p v-if="product.category" class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ product.category.name }}</p>
      <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="mt-0.5 line-clamp-2 font-semibold text-slate-800 hover:text-brand-700">
        {{ product.name }}
      </RouterLink>
      <div v-if="product.reviews_count" class="mt-1 flex items-center gap-1 text-sm">
        <StarRating :value="product.rating" size="text-sm" />
        <span class="text-slate-400">{{ product.rating }} ({{ product.reviews_count }})</span>
      </div>
      <p class="mt-1 font-display text-lg font-bold text-brand-700">{{ money(product.listed_price) }}</p>

      <div v-if="product.allow_draw && product.open_batch" class="mt-2">
        <DrawProgress :filled="product.open_batch.filled" :size="product.open_batch.size" compact />
      </div>

      <div class="mt-3 flex gap-2">
        <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="btn-ghost flex-1 text-sm">View</RouterLink>
        <button v-if="product.allow_full_buy" class="btn-primary text-sm" @click="addToCart">Add</button>
      </div>
    </div>
  </div>
</template>
