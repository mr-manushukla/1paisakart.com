<script setup>
import { computed } from 'vue'
import { useWishlistStore } from '../stores/wishlist'
import { toast } from '../lib/toast'

const props = defineProps({
  product: { type: Object, required: true },
  floating: Boolean, // absolute pill for use over a card image
})
const wl = useWishlistStore()
const wished = computed(() => wl.has(props.product.id))

async function toggle() {
  const now = await wl.toggle(props.product)
  toast(now ? 'Saved to your list ♥' : 'Removed from your list')
}
</script>

<template>
  <button
    type="button"
    :aria-pressed="wished"
    :aria-label="wished ? 'Remove from saved' : 'Save for later'"
    class="flex items-center justify-center rounded-full transition hover:scale-110"
    :class="floating ? 'h-9 w-9 bg-white/90 shadow' : 'h-11 w-11 border border-slate-200 bg-white'"
    @click.stop.prevent="toggle"
  >
    <span class="text-xl leading-none" :class="wished ? 'text-rose-500' : 'text-slate-400'">{{ wished ? '♥' : '♡' }}</span>
  </button>
</template>
