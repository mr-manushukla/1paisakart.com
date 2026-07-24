<script setup>
import { RouterLink } from 'vue-router'
import ProductImage from './ProductImage.vue'
import DrawProgress from './DrawProgress.vue'
import StarRating from './StarRating.vue'
import WishlistHeart from './WishlistHeart.vue'
import { money } from '../lib/money'
import { useCartStore } from '../stores/cart'
import { toast } from '../lib/toast'
import { openBuyOptions } from '../lib/buyOptions'

const props = defineProps({ product: { type: Object, required: true } })
const cart = useCartStore()

/**
 * There are two ways to buy, so "Add" asks which one — unless only one applies,
 * in which case asking would just be an extra click.
 */
function onAdd() {
  const canBuy = props.product.allow_full_buy && props.product.stock > 0
  // Already holding a 1% seat for this item? Don't offer it again (one per pool).
  const canDraw = !!props.product.draw_eligible && !props.product.already_booked

  if (canBuy && canDraw) return openBuyOptions(props.product)

  if (canBuy) {
    cart.add(props.product, 1, 'buy')
    return toast(`${props.product.name} added to cart`)
  }
  if (canDraw) {
    cart.add(props.product, 1, 'draw')
    return toast('1% booking added to cart')
  }
  toast('This product isn’t available right now', 'error')
}
</script>

<template>
  <div class="card flex flex-col overflow-hidden p-3 transition hover:shadow-md">
    <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="relative block">
      <ProductImage :src="product.image" :name="product.name" />
      <WishlistHeart :product="product" floating class="absolute right-2 top-2" />
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
      <div class="mt-1 flex flex-wrap items-baseline gap-1.5">
        <span class="font-display text-lg font-bold text-brand-700">{{ money(product.listed_price) }}</span>
        <template v-if="product.mrp">
          <span class="text-xs text-slate-400 line-through">{{ money(product.mrp) }}</span>
          <span class="text-xs font-bold text-green-700">↓{{ product.discount_pct }}%</span>
        </template>
      </div>

      <div v-if="product.draw_eligible && product.open_batch" class="mt-2">
        <DrawProgress :filled="product.open_batch.filled" :size="product.open_batch.size" compact />
      </div>

      <div class="mt-3 flex gap-2">
        <RouterLink :to="{ name: 'product', params: { slug: product.slug } }" class="btn-ghost flex-1 text-sm">View</RouterLink>
        <button v-if="product.allow_full_buy || product.draw_eligible" class="btn-primary text-sm" @click="onAdd">Add</button>
      </div>
    </div>
  </div>
</template>
