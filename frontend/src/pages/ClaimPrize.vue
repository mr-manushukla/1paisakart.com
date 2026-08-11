<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'
import { toast, apiError } from '../lib/toast'
import { payAndFulfil } from '../lib/razorpay'
import AddressBook from '../components/AddressBook.vue'
import ProductImage from '../components/ProductImage.vue'

/**
 * Order summary for a won prize. The product itself is already paid for by the
 * 1% advance — what's settled here is the tax on the prize, which the platform
 * has to deposit before the prize can be released.
 */
const route = useRoute()
const router = useRouter()

const loading = ref(true)
const claiming = ref(false)
const entry = ref(null)
const quote = ref(null)
const claimedAt = ref(null)
const addressId = ref(null)
const showCalc = ref(false)

onMounted(async () => {
  try {
    const { data } = await api.get(`/draw-entries/${route.params.entry}/claim`)
    entry.value = data.entry.data ?? data.entry
    quote.value = data.quote
    claimedAt.value = data.claimed_at
  } catch (e) {
    toast(apiError(e, 'That prize could not be loaded'), 'error')
    router.push({ name: 'orders' })
  } finally {
    loading.value = false
  }
})

async function claim() {
  if (!addressId.value) return toast('Choose a delivery address first', 'error')
  claiming.value = true
  try {
    // Nothing to pay (TDS switched off) → claim outright, no gateway round trip.
    if (!quote.value.payable) {
      await api.post(`/draw-entries/${route.params.entry}/claim`, { address_id: addressId.value })
    } else {
      const result = await payAndFulfil({
        intent: 'claim',
        entry_id: Number(route.params.entry),
        address_id: addressId.value,
      })
      if (!result) return toast('Payment cancelled — your prize is still yours to claim', 'error')
    }
    toast('Prize claimed 🎉 We’ll dispatch it to your address.')
    router.push({ name: 'orders' })
  } catch (e) {
    toast(apiError(e, e?.message || 'Claim failed'), 'error')
  } finally {
    claiming.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-4xl">
    <h1 class="mb-1 font-display text-2xl font-bold">Claim your prize 🏆</h1>
    <p class="mb-6 text-sm text-slate-500">
      Confirm where to send it and settle the tax on the prize — then it goes out for dispatch.
    </p>

    <div v-if="loading" class="card h-64 animate-pulse bg-slate-50" />

    <div v-else-if="claimedAt" class="card p-10 text-center">
      <p class="text-lg font-semibold text-brand-700">This prize is already claimed.</p>
      <p class="mt-1 text-sm text-slate-500">It's on its way — track it in your orders.</p>
      <RouterLink to="/orders" class="btn-primary mt-4 inline-block">Go to my orders</RouterLink>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-[1fr_340px]">
      <div class="space-y-4">
        <!-- What was won -->
        <div class="card flex gap-4 p-4">
          <div class="h-20 w-20 flex-none">
            <ProductImage :src="entry.product?.image" :name="entry.product?.name || ''" />
          </div>
          <div class="min-w-0">
            <p class="font-semibold">{{ entry.product?.name }}</p>
            <p class="mt-0.5 text-sm text-slate-500">
              {{ entry.pool?.club }} · pool #{{ entry.pool?.batch_no }}
            </p>
            <p class="mt-1 text-sm text-brand-700">
              Won for your {{ money(entry.advance) }} advance — the balance is on us.
            </p>
          </div>
        </div>

        <div class="card p-4">
          <h2 class="mb-3 font-semibold">Delivery address</h2>
          <AddressBook v-model="addressId" selectable />
        </div>
      </div>

      <div class="card h-fit p-5">
        <h2 class="font-semibold">Summary</h2>

        <div class="mt-4 space-y-1 text-sm">
          <div class="flex justify-between">
            <span class="text-slate-500">Product price</span>
            <span>{{ money(quote.prize_value) }}</span>
          </div>
          <div class="flex justify-between text-slate-400">
            <span>Already paid (1% advance)</span>
            <span>− {{ money(quote.advance_paid) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">TDS @ {{ quote.tds_pct }}%</span>
            <span>{{ money(quote.tds_amount) }}</span>
          </div>
          <div class="mt-2 flex justify-between border-t border-slate-100 pt-2 text-base font-bold">
            <span>Total payable</span><span>{{ money(quote.payable) }}</span>
          </div>
        </div>

        <button type="button" class="btn-ghost mt-3 w-full text-sm" @click="showCalc = true">
          🧮 TDS calculator
        </button>

        <button class="btn-primary mt-3 w-full" :disabled="claiming" @click="claim">
          {{ claiming ? 'Processing…' : (quote.payable ? `Pay ${money(quote.payable)} & claim` : 'Claim prize') }}
        </button>
        <p class="mt-2 text-center text-xs text-slate-400">
          The product costs you nothing more — only the tax is payable.
        </p>
      </div>
    </div>

    <!-- How the TDS was worked out -->
    <div
      v-if="showCalc"
      class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
      @click.self="showCalc = false"
    >
      <div class="w-full max-w-md rounded-2xl bg-white p-5">
        <div class="flex items-start justify-between">
          <h3 class="font-display text-lg font-bold">How your TDS is calculated</h3>
          <button class="text-slate-400 hover:text-slate-600" @click="showCalc = false">✕</button>
        </div>

        <p class="mt-2 text-sm text-slate-600">
          A prize won in a draw is taxable under section 194B of the Income Tax Act. Because the
          prize is goods rather than cash, there's nothing for us to deduct it from — so you pay it
          here and we deposit it with the government on your behalf.
        </p>

        <div class="mt-4 space-y-2 rounded-xl bg-slate-50 p-4 text-sm">
          <div class="flex justify-between">
            <span class="text-slate-500">Prize value</span>
            <span class="font-medium">{{ money(quote.prize_value) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">TDS rate</span><span class="font-medium">{{ quote.tds_pct }}%</span>
          </div>
          <div class="border-t border-slate-200 pt-2 text-center text-xs text-slate-500">
            {{ money(quote.prize_value) }} × {{ quote.tds_pct }}%
          </div>
          <div class="flex justify-between text-base">
            <span class="font-semibold">Payable to the government</span>
            <span class="font-bold text-brand-700">{{ money(quote.tds_amount) }}</span>
          </div>
        </div>

        <p class="mt-3 text-xs text-slate-400">
          Figures are indicative. Your own tax position may differ — please check with your tax adviser.
        </p>
        <button class="btn-primary mt-4 w-full" @click="showCalc = false">Got it</button>
      </div>
    </div>
  </div>
</template>
