<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: { type: Number, default: 0 },
  interactive: Boolean,
  size: { type: String, default: 'text-base' },
})
const emit = defineEmits(['update:value'])

// Rounded index for display; interactive mode sets whole stars.
const rounded = computed(() => Math.round(props.value))
</script>

<template>
  <div class="inline-flex items-center" :class="size" role="img" :aria-label="`${value} out of 5 stars`">
    <button
      v-for="i in 5"
      :key="i"
      type="button"
      :disabled="!interactive"
      class="leading-none"
      :class="[interactive ? 'cursor-pointer px-0.5' : 'cursor-default', i <= rounded ? 'text-accent-500' : 'text-slate-300']"
      :aria-label="interactive ? `Rate ${i}` : undefined"
      @click="interactive && emit('update:value', i)"
    >★</button>
  </div>
</template>
