<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../lib/api'

/**
 * Horizontal, scrollable strip of pool price bands. Tapping one filters the
 * catalogue to that band. The bands come from the backend, so a club added
 * there shows up here with no frontend change.
 *
 * A band maps onto the existing min_price/max_price filters rather than a new
 * "club" parameter — same query, one less thing to keep in sync.
 */
const props = defineProps({
  min: { type: [Number, String], default: null }, // active range, in paise
  max: { type: [Number, String], default: null },
})
const emit = defineEmits(['select'])

const all = ref([])

// Only bands that actually hold products. There are ~100 configured bands, and
// a chip that leads to "no products found" is worse than no chip.
const clubs = computed(() => all.value.filter((c) => c.products_count > 0))

onMounted(async () => {
  try {
    all.value = (await api.get('/clubs')).data.data
  } catch { /* the bar just stays empty — never block the catalogue */ }
})

const activeId = computed(() => {
  const lo = Number(props.min), hi = Number(props.max)
  if (!lo && !hi) return 'all'
  return clubs.value.find((c) => c.min_price === lo && c.max_price === hi)?.id ?? null
})

/** "₹1,001 – ₹5,000" — the label the admin already stores on the club. */
const shortLabel = (c) => c.label
</script>

<template>
  <div v-if="clubs.length" class="-mx-4 px-4 sm:mx-0 sm:px-0">
    <div class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
      <button
        type="button"
        class="chip shrink-0 whitespace-nowrap border transition"
        :class="activeId === 'all' ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-brand-400'"
        @click="emit('select', null)"
      >All pools</button>

      <button
        v-for="c in clubs"
        :key="c.id"
        type="button"
        class="chip shrink-0 whitespace-nowrap border transition"
        :class="activeId === c.id ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-brand-400'"
        @click="emit('select', c)"
      >{{ shortLabel(c) }}</button>
    </div>
  </div>
</template>
