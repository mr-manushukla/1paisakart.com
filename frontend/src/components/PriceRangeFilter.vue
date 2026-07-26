<script setup>
import { ref, computed, watch } from 'vue'

/**
 * Dual-handle price range: two overlaid sliders plus number inputs, all bound to
 * the same pair of values, so dragging updates the boxes and typing moves the
 * handles. Values are RUPEES here; the parent converts to paise for the API.
 */
const props = defineProps({
  min: { type: Number, default: 0 },      // bounds, in rupees
  max: { type: Number, default: 100000 },
  modelValue: { type: Object, default: () => ({ from: null, to: null }) },
})
const emit = defineEmits(['update:modelValue', 'apply'])

const from = ref(props.modelValue.from ?? props.min)
const to = ref(props.modelValue.to ?? props.max)

// Follow the bounds when the catalogue changes (e.g. a new delivery PIN).
watch(() => [props.min, props.max], ([lo, hi]) => {
  if (from.value < lo || from.value > hi) from.value = lo
  if (to.value > hi || to.value < lo) to.value = hi
})
watch(() => props.modelValue, (v) => {
  from.value = v.from ?? props.min
  to.value = v.to ?? props.max
})

const clamp = (n, lo, hi) => Math.min(hi, Math.max(lo, Math.round(Number(n) || 0)))

// The handles must never cross, whichever control moved.
function setFrom(v) {
  from.value = clamp(v, props.min, to.value)
  push()
}
function setTo(v) {
  to.value = clamp(v, from.value, props.max)
  push()
}
function push() {
  emit('update:modelValue', { from: from.value, to: to.value })
}

const pctFrom = computed(() => ((from.value - props.min) / Math.max(1, props.max - props.min)) * 100)
const pctTo = computed(() => ((to.value - props.min) / Math.max(1, props.max - props.min)) * 100)
const dirty = computed(() => from.value > props.min || to.value < props.max)

function reset() {
  from.value = props.min
  to.value = props.max
  push()
  emit('apply')
}
</script>

<template>
  <div>
    <div class="mb-3 flex items-center justify-between">
      <h3 class="font-semibold">Price</h3>
      <button v-if="dirty" class="text-xs text-slate-500 hover:text-rose-600" @click="reset">Reset</button>
    </div>

    <!-- Two range inputs stacked on one track; only the thumbs take pointer events -->
    <div class="relative h-6">
      <div class="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-full bg-slate-200" />
      <div
        class="absolute top-1/2 h-1 -translate-y-1/2 rounded-full bg-brand-500"
        :style="{ left: pctFrom + '%', right: (100 - pctTo) + '%' }"
      />
      <input
        :value="from" type="range" :min="min" :max="max" step="1"
        class="range-thumb absolute inset-x-0 top-0 h-6 w-full appearance-none bg-transparent"
        aria-label="Minimum price"
        @input="setFrom($event.target.value)" @change="emit('apply')"
      />
      <input
        :value="to" type="range" :min="min" :max="max" step="1"
        class="range-thumb absolute inset-x-0 top-0 h-6 w-full appearance-none bg-transparent"
        aria-label="Maximum price"
        @input="setTo($event.target.value)" @change="emit('apply')"
      />
    </div>

    <!-- Manual entry, kept in sync with the handles -->
    <div class="mt-3 flex items-center gap-2">
      <label class="flex-1">
        <span class="sr-only">Minimum price</span>
        <input
          :value="from" type="number" inputmode="numeric" :min="min" :max="max"
          class="input py-1.5 text-sm" placeholder="Min"
          @change="setFrom($event.target.value); emit('apply')"
        />
      </label>
      <span class="text-slate-400">–</span>
      <label class="flex-1">
        <span class="sr-only">Maximum price</span>
        <input
          :value="to" type="number" inputmode="numeric" :min="min" :max="max"
          class="input py-1.5 text-sm" placeholder="Max"
          @change="setTo($event.target.value); emit('apply')"
        />
      </label>
    </div>
    <p class="mt-1 text-xs text-slate-500">₹{{ from.toLocaleString('en-IN') }} – ₹{{ to.toLocaleString('en-IN') }}</p>
  </div>
</template>

<style scoped>
/* Let clicks fall through the track to whichever thumb is under the cursor. */
.range-thumb { pointer-events: none; }
.range-thumb::-webkit-slider-thumb {
  pointer-events: auto;
  -webkit-appearance: none;
  height: 1rem; width: 1rem;
  border-radius: 9999px;
  background: #fff;
  border: 2px solid var(--color-brand-600, #059669);
  cursor: pointer;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.2);
}
.range-thumb::-moz-range-thumb {
  pointer-events: auto;
  height: 1rem; width: 1rem;
  border-radius: 9999px;
  background: #fff;
  border: 2px solid var(--color-brand-600, #059669);
  cursor: pointer;
}
</style>
