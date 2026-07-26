<script setup>
import { ref, computed } from 'vue'
import { useDeliveryStore } from '../stores/delivery'
import { toast } from '../lib/toast'

/** Amazon-style delivery location picker: pick a PIN, see only what ships there. */
const delivery = useDeliveryStore()
const open = ref(false)
const draft = ref(delivery.pincode)

const label = computed(() => (delivery.active ? `Deliver to ${delivery.pincode}` : 'Select delivery PIN'))

function apply() {
  const clean = String(draft.value).replace(/\D/g, '')
  if (clean.length !== 6) return toast('Enter a valid 6-digit PIN code', 'error')
  delivery.set(clean)
  open.value = false
  toast(`Showing products delivering to ${clean}`)
}
function clear() {
  delivery.clear()
  draft.value = ''
  open.value = false
  toast('Showing all products')
}
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="flex max-w-full items-center gap-1 truncate text-xs text-slate-600 hover:text-brand-700"
      @click="open = !open"
    >
      <span class="text-sm leading-none">📍</span>
      <span class="truncate font-medium">{{ label }}</span>
      <span class="text-slate-400">▾</span>
    </button>

    <div v-if="open" class="absolute left-0 z-50 mt-2 w-64 rounded-xl border border-slate-100 bg-white p-3 shadow-lg">
      <p class="text-sm font-semibold">Choose your location</p>
      <p class="mt-0.5 text-xs text-slate-500">We'll only show products that deliver to your PIN code.</p>
      <form class="mt-2 flex gap-2" @submit.prevent="apply">
        <input
          v-model="draft"
          inputmode="numeric"
          maxlength="6"
          class="input py-1.5 text-sm"
          placeholder="6-digit PIN"
          aria-label="Delivery PIN code"
        />
        <button class="btn-primary px-3 py-1.5 text-sm">Apply</button>
      </form>
      <button v-if="delivery.active" type="button" class="mt-2 text-xs text-slate-500 hover:text-rose-600" @click="clear">
        Clear — show all products
      </button>
    </div>
  </div>
</template>
