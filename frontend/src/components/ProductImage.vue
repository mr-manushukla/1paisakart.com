<script setup>
import { computed } from 'vue'

const props = defineProps({ src: String, name: { type: String, default: '' } })

// Deterministic gradient from the name so each product gets a stable colour.
const hue = computed(() => {
  let h = 0
  for (const c of props.name) h = (h * 31 + c.charCodeAt(0)) % 360
  return h
})
const initials = computed(() =>
  props.name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
)
</script>

<template>
  <div class="relative aspect-square w-full overflow-hidden rounded-xl bg-slate-100">
    <img v-if="src" :src="src" :alt="name" class="h-full w-full object-cover" />
    <div
      v-else
      class="flex h-full w-full items-center justify-center font-display text-4xl font-bold text-white/90"
      :style="{ background: `linear-gradient(135deg, hsl(${hue} 65% 55%), hsl(${(hue + 40) % 360} 70% 40%))` }"
    >
      {{ initials }}
    </div>
  </div>
</template>
