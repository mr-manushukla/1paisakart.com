<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'

const cats = ref([])
const icons = { 'Electronics': '📱', 'Fashion': '👕', 'Home & Kitchen': '🍳', 'Grocery': '🛒' }

onMounted(async () => { cats.value = (await api.get('/categories')).data })
</script>

<template>
  <section v-if="cats.length">
    <h2 class="mb-4 font-display text-2xl font-bold">Shop by category</h2>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <RouterLink
        v-for="c in cats"
        :key="c.id"
        :to="{ name: 'shop', query: { category: c.slug } }"
        class="card flex flex-col items-center gap-2 p-6 transition hover:-translate-y-0.5 hover:shadow-md"
      >
        <span class="text-4xl">{{ icons[c.name] || '🏷️' }}</span>
        <span class="font-semibold">{{ c.name }}</span>
        <span class="text-xs text-slate-400">{{ c.products_count }} items</span>
      </RouterLink>
    </div>
  </section>
</template>
