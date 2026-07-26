<script setup>
import { computed } from 'vue'
import { money } from '../lib/money'

const props = defineProps({
  filled: { type: Number, default: 0 },
  size: { type: Number, default: 100 },
  entryPrice: { type: Number, default: null },
  compact: Boolean,
})

/**
 * Fill progress stays hidden until the pool is half full: an almost-empty bar
 * reads as "nobody's here" and puts people off. Past the threshold it becomes
 * the opposite signal — social proof — so we show it with an urgency line.
 */
const REVEAL_AT_PCT = 50

const pct = computed(() => Math.min(100, Math.round((props.filled / props.size) * 100)))
const remaining = computed(() => Math.max(0, props.size - props.filled))
const show = computed(() => pct.value >= REVEAL_AT_PCT)
const urgency = computed(() =>
  pct.value >= 90 ? 'Almost gone — only a few seats left!'
    : pct.value >= 75 ? 'Filling fast — the draw happens the moment it’s full.'
      : 'Hurry! The pool is filling up fast.')
</script>

<template>
  <div v-if="show">
    <div class="mb-1 flex items-center justify-between text-sm">
      <span class="font-semibold text-brand-700">{{ filled }} / {{ size }} joined</span>
      <span v-if="entryPrice != null" class="chip bg-accent-500/10 text-accent-600">
        Book for {{ money(entryPrice) }}
      </span>
    </div>
    <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
      <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-600 transition-all" :style="{ width: pct + '%' }" />
    </div>
    <p class="mt-1 text-xs font-semibold text-accent-600">🔥 {{ urgency }}</p>
    <p v-if="!compact" class="mt-0.5 text-xs text-slate-500">
      {{ remaining }} {{ remaining === 1 ? 'seat' : 'seats' }} left — when the pool fills, one winner keeps their product for the 1%.
    </p>
  </div>

  <!-- Below the threshold: invite them in without advertising how empty it is. -->
  <div v-else-if="!compact" class="text-xs text-slate-500">
    <span v-if="entryPrice != null" class="chip bg-accent-500/10 text-accent-600">Book for {{ money(entryPrice) }}</span>
    <span class="ml-1">Pool is open — one winner in {{ size }} keeps their product for the 1%.</span>
  </div>
</template>
