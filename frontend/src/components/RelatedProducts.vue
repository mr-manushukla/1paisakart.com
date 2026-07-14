<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '../lib/api'
import ProductCard from './ProductCard.vue'

const props = defineProps({
  slug: { type: String, required: true },
  title: { type: String, default: 'You may also like' },
})
const items = ref([])

async function load() {
  if (!props.slug) return
  const { data } = await api.get(`/products/${props.slug}/related`)
  items.value = data.data
}
onMounted(load)
watch(() => props.slug, load)
</script>

<template>
  <section v-if="items.length">
    <h2 class="mb-4 font-display text-xl font-bold">{{ title }}</h2>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
      <ProductCard v-for="p in items" :key="p.id" :product="p" />
    </div>
  </section>
</template>
