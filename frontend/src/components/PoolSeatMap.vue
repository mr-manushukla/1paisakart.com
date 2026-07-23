<script setup>
import { computed, ref } from 'vue'
import { money } from '../lib/money'

/**
 * Seat-map view of a club pool, in the spirit of a cinema booking screen:
 * every seat in the pool is drawn, so "how full is this" is one glance rather
 * than a number to read. Booked seats carry the (masked) member and the
 * product they booked — tap one to see it.
 */
const props = defineProps({
  size: { type: Number, default: 100 },
  filled: { type: Number, default: 0 },
  participants: { type: Array, default: () => [] },
  winnerSeat: { type: Number, default: null },
  entryPrice: { type: Number, default: null },
})

const selected = ref(null)

// seat number -> participant, so the map can render in one pass.
const bySeat = computed(() =>
  Object.fromEntries(props.participants.map((p) => [p.seat, p])))

const seats = computed(() =>
  Array.from({ length: props.size }, (_, i) => {
    const seat = i + 1
    return { seat, who: bySeat.value[seat] ?? null, won: props.winnerSeat === seat }
  }))

const available = computed(() => Math.max(0, props.size - props.filled))
const pct = computed(() => Math.min(100, Math.round((props.filled / props.size) * 100)))

function pick(s) {
  selected.value = s.who && selected.value?.seat !== s.seat ? s.who : null
}
</script>

<template>
  <div>
    <!-- Counts first: the question is "how many seats are left". -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-baseline gap-2">
        <span class="font-display text-2xl font-extrabold text-brand-700">{{ filled }}</span>
        <span class="text-sm text-slate-500">booked</span>
        <span class="text-slate-300">·</span>
        <span class="font-display text-2xl font-extrabold text-slate-400">{{ available }}</span>
        <span class="text-sm text-slate-500">available</span>
      </div>
      <span v-if="entryPrice != null" class="chip bg-accent-500/10 text-accent-600">
        {{ money(entryPrice) }} a seat
      </span>
    </div>

    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
      <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-600 transition-all" :style="{ width: pct + '%' }" />
    </div>

    <!-- Legend -->
    <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-500">
      <span class="flex items-center gap-1.5"><span class="h-3.5 w-3.5 rounded bg-brand-600" />Booked</span>
      <span class="flex items-center gap-1.5"><span class="h-3.5 w-3.5 rounded border border-dashed border-slate-300 bg-white" />Available</span>
      <span v-if="winnerSeat" class="flex items-center gap-1.5"><span class="h-3.5 w-3.5 rounded bg-accent-500" />Winner</span>
    </div>

    <!-- The map -->
    <div class="mt-3 grid grid-cols-10 gap-1 sm:grid-cols-[repeat(20,minmax(0,1fr))] sm:gap-1.5">
      <button
        v-for="s in seats"
        :key="s.seat"
        type="button"
        class="flex aspect-square items-center justify-center rounded text-[9px] font-semibold transition sm:text-[10px]"
        :class="s.won ? 'bg-accent-500 text-white ring-2 ring-accent-500/40'
          : s.who ? 'bg-brand-600 text-white hover:bg-brand-700'
          : 'border border-dashed border-slate-300 bg-white text-slate-300'"
        :title="s.who ? `Seat ${s.seat} · ${s.who.name} · ${s.who.product}` : `Seat ${s.seat} — available`"
        :aria-label="s.who ? `Seat ${s.seat}, booked by ${s.who.name} for ${s.who.product}` : `Seat ${s.seat}, available`"
        :aria-pressed="selected?.seat === s.seat"
        @click="pick(s)"
      >
        {{ s.seat }}
      </button>
    </div>

    <!-- Tapping a booked seat reveals it here — works on touch, where :title never fires. -->
    <p v-if="selected" class="mt-3 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-800">
      <span class="font-semibold">Seat {{ selected.seat }}</span> · {{ selected.name }} booked
      <span class="font-medium">{{ selected.product }}</span> for {{ money(selected.advance) }}
    </p>
    <p v-else-if="available" class="mt-3 text-xs text-slate-500">
      Tap a booked seat to see who took it. {{ available }} {{ available === 1 ? 'seat is' : 'seats are' }} still open —
      when the pool fills, one winner keeps their product for the 1%.
    </p>
  </div>
</template>
