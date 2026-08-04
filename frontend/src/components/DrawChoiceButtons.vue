<script setup>
import { money } from '../lib/money'

/**
 * The two options a non-winner picks between. Used on My Draws and My Orders so
 * the wording and behaviour are identical wherever the choice is offered.
 *
 * The 99% side can either pay immediately or drop into the cart, so several
 * bookings can be settled in one checkout.
 */
defineProps({
  entry: { type: Object, required: true },
  busy: Boolean,
  inCart: Boolean,
  /** Show "Add to cart" next to the pay button (My Draws does; My Orders doesn't). */
  allowCart: Boolean,
})
const emit = defineEmits(['pay', 'wallet', 'cart'])
</script>

<template>
  <div class="grid gap-2 sm:grid-cols-2">
    <!-- Choose 99%: take the product, paying the rest of its price -->
    <div class="flex h-full flex-col rounded-xl border-2 border-brand-600 bg-brand-50/50 px-3 py-2">
      <span class="text-sm font-semibold text-brand-800">Buy Now @ product cost</span>
      <span class="text-xs text-slate-500">(After deduction of 1% Advance)</span>
      <span class="mt-1 text-sm font-bold text-brand-700">{{ money(entry.balance_due) }}</span>

      <div class="mt-2 flex flex-wrap gap-2">
        <button class="btn-primary px-3 py-1.5 text-xs" :disabled="busy" @click="emit('pay')">
          {{ busy ? 'Processing…' : 'Pay now' }}
        </button>
        <button
          v-if="allowCart"
          class="btn-ghost px-3 py-1.5 text-xs"
          :disabled="busy || inCart"
          @click="emit('cart')"
        >{{ inCart ? '✓ In cart' : 'Add to cart' }}</button>
      </div>
    </div>

    <!-- Choose 1%: keep the advance as credit instead -->
    <div class="flex h-full flex-col rounded-xl border-2 border-accent-500/50 bg-accent-500/5 px-3 py-2">
      <span class="text-sm font-semibold text-accent-700">Move 1% Advance into 1% Wallet</span>
      <span class="text-xs text-slate-500">Buy any product at any time.</span>
      <span class="mt-1 text-sm font-bold text-accent-700">{{ money(entry.advance) }}</span>

      <div class="mt-2">
        <button class="btn-ghost px-3 py-1.5 text-xs" :disabled="busy" @click="emit('wallet')">
          {{ busy ? 'Processing…' : 'Move to wallet' }}
        </button>
      </div>
    </div>
  </div>
</template>
