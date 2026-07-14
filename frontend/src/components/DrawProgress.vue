<script setup>
import { computed } from 'vue'
import { money } from '../lib/money'

const props = defineProps({
  filled: { type: Number, default: 0 },
  size: { type: Number, default: 100 },
  entryPrice: { type: Number, default: null },
  compact: Boolean,
})

const pct = computed(() => Math.min(100, Math.round((props.filled / props.size) * 100)))
const remaining = computed(() => Math.max(0, props.size - props.filled))
</script>

<template>
  <div>
    <div class="mb-1 flex items-center justify-between text-sm">
      <span class="font-semibold text-brand-700">{{ filled }} / {{ size }} joined</span>
      <span v-if="entryPrice != null" class="chip bg-accent-500/10 text-accent-600">
        Join for {{ money(entryPrice) }}
      </span>
    </div>
    <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
      <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-600 transition-all" :style="{ width: pct + '%' }" />
    </div>
    <p v-if="!compact" class="mt-1 text-xs text-slate-500">
      {{ remaining }} {{ remaining === 1 ? 'seat' : 'seats' }} left — when the pool fills, one winner takes it for 1%.
    </p>
  </div>
</template>
