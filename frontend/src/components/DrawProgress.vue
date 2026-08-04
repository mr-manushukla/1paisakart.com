<script setup>
import { computed } from 'vue'
import { money } from '../lib/money'
import { poolPct, poolRevealed, poolUrgency } from '../lib/pool'

const props = defineProps({
  filled: { type: Number, default: 0 },
  size: { type: Number, default: 100 },
  entryPrice: { type: Number, default: null },
  compact: Boolean,
})

// Threshold and copy live in lib/pool.js — shared with the seat map so the two
// can never disagree about whether a pool's fill level is public.
const pct = computed(() => poolPct(props.filled, props.size))
const remaining = computed(() => Math.max(0, props.size - props.filled))
const show = computed(() => poolRevealed(props.filled, props.size))
const urgency = computed(() => poolUrgency(props.filled, props.size))
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
