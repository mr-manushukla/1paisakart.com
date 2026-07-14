<script setup>
// Stepper with a free-text input (research: steppers beat dropdowns; keep typing).
const props = defineProps({
  modelValue: { type: Number, default: 1 },
  max: { type: Number, default: 99 },
})
const emit = defineEmits(['update:modelValue'])

function set(v) {
  const n = Math.max(1, Math.min(props.max, Math.floor(Number(v) || 1)))
  emit('update:modelValue', n)
}
</script>

<template>
  <div class="inline-flex items-center rounded-lg border border-slate-200">
    <button type="button" class="h-11 w-11 text-lg text-slate-600 disabled:opacity-40" :disabled="modelValue <= 1" aria-label="Decrease quantity" @click="set(modelValue - 1)">−</button>
    <input
      :value="modelValue"
      type="number"
      min="1"
      :max="max"
      inputmode="numeric"
      class="h-11 w-14 border-x border-slate-200 text-center outline-none"
      aria-label="Quantity"
      @focus="$event.target.select()"
      @change="set($event.target.value)"
    />
    <button type="button" class="h-11 w-11 text-lg text-slate-600 disabled:opacity-40" :disabled="modelValue >= max" aria-label="Increase quantity" @click="set(modelValue + 1)">+</button>
  </div>
</template>
