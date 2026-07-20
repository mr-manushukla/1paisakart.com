<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '../lib/api'

const route = useRoute()
const cats = ref([])
const icons = { 'Electronics': '📱', 'Fashion': '👕', 'Home & Kitchen': '🍳', 'Grocery': '🛒' }

onMounted(async () => {
  try { cats.value = (await api.get('/categories')).data } catch { /* strip just stays empty */ }
})

// "For You" is active on the home page and on an unfiltered shop listing.
const onAll = computed(() => route.name === 'home' || (route.name === 'shop' && !route.query.category))
const isActive = (slug) => route.name === 'shop' && route.query.category === slug
</script>

<template>
  <nav class="no-scrollbar flex gap-1 overflow-x-auto px-2" aria-label="Categories">
    <RouterLink
      to="/"
      class="flex min-w-16 flex-none flex-col items-center gap-0.5 border-b-2 px-2 pb-1.5 pt-1 text-[11px] font-medium transition"
      :class="onAll ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'"
    >
      <span class="text-xl leading-none">✨</span>
      <span>For You</span>
    </RouterLink>

    <RouterLink
      v-for="c in cats"
      :key="c.id"
      :to="{ name: 'shop', query: { category: c.slug } }"
      class="flex min-w-16 flex-none flex-col items-center gap-0.5 border-b-2 px-2 pb-1.5 pt-1 text-[11px] font-medium transition"
      :class="isActive(c.slug) ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'"
    >
      <span class="text-xl leading-none">{{ icons[c.name] || '🏷️' }}</span>
      <span class="max-w-16 truncate">{{ c.name }}</span>
    </RouterLink>
  </nav>
</template>
