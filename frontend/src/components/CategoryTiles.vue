<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'
import { categoryIcon } from '../lib/categoryIcons'

const cats = ref([])
const showAll = ref(false)

// How many fit before "Show all" appears — two full rows on the widest grid.
const INITIAL = 12

const visible = computed(() => (showAll.value ? cats.value : cats.value.slice(0, INITIAL)))

onMounted(async () => { cats.value = (await api.get('/categories')).data })
</script>

<template>
  <section v-if="cats.length">
    <div class="mb-4 flex items-end justify-between">
      <h2 class="font-display text-2xl font-bold">Shop by category</h2>
      <button
        v-if="cats.length > INITIAL"
        class="text-sm font-semibold text-brand-700 hover:underline"
        @click="showAll = !showAll"
      >{{ showAll ? 'Show less' : `Show all ${cats.length}` }}</button>
    </div>

    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
      <RouterLink
        v-for="c in visible"
        :key="c.id"
        :to="{ name: 'shop', query: { category: c.slug } }"
        class="card flex flex-col items-center gap-1.5 p-4 text-center transition hover:-translate-y-0.5 hover:shadow-md"
      >
        <span class="text-3xl">{{ categoryIcon(c) }}</span>
        <span class="text-sm font-semibold leading-tight">{{ c.name }}</span>
        <span class="text-xs text-slate-400">{{ c.products_count }} items</span>
      </RouterLink>
    </div>
  </section>
</template>
