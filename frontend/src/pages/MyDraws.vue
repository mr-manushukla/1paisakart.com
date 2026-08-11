<script setup>
import { ref, onMounted, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'
import { payAndFulfil } from '../lib/razorpay'
import ProductImage from '../components/ProductImage.vue'
import DrawChoiceButtons from '../components/DrawChoiceButtons.vue'
import PeriodFilter from '../components/PeriodFilter.vue'
import { useCartStore } from '../stores/cart'

const cart = useCartStore()
const auth = useAuthStore()

/** Queue this booking's 99% balance so several can be paid in one checkout. */
function addToCart(e) {
  cart.addBalance(e)
  toast(`${e.product?.name ?? 'Booking'} added to cart — pay for several at once`)
}
const router = useRouter()
const entries = ref([])
const loading = ref(true)
const busy = ref(null)

const badge = {
  active: ['In the pool', 'bg-brand-50 text-brand-700'],
  won: ['🎉 You won!', 'bg-accent-500 text-white'],
  lost_pending: ['Choose an option', 'bg-amber-50 text-amber-700'],
  converted: ['Purchased', 'bg-brand-100 text-brand-800'],
  credited: ['Moved to 1% Wallet', 'bg-slate-100 text-slate-600'],
  refunded: ['Refunded', 'bg-slate-100 text-slate-600'],
}

const summary = ref(null)

// Bookings can span years — filter by month, year or a custom range. Wins get
// their own toggle so a winner never has to hunt for one among the losing seats.
const period = ref({ from: null, to: null })
const wonOnly = ref(false)
const page = ref({ current: 1, last: 1 })
const loadingMore = ref(false)

async function load(p = 1) {
  const { data } = await api.get('/my-draws', {
    params: {
      status: wonOnly.value ? 'won' : undefined,
      from: period.value.from || undefined,
      to: period.value.to || undefined,
      page: p,
    },
  })
  entries.value = p === 1 ? data.data : [...entries.value, ...data.data]
  page.value = { current: data.meta?.current_page ?? 1, last: data.meta?.last_page ?? 1 }
  summary.value = data.summary
}

async function more() {
  loadingMore.value = true
  try { await load(page.value.current + 1) } finally { loadingMore.value = false }
}

watch([period, wonOnly], () => load(1))
onMounted(async () => { try { await load() } finally { loading.value = false } })

const deadline = (iso) => new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })

async function payBalance(e) {
  busy.value = e.id
  try {
    const result = await payAndFulfil({ intent: 'balance', entry_id: e.id, apply_wallet: true })
    if (!result) return toast('Payment cancelled', 'error')

    toast('Payment successful — the product is yours 🎉')
    await Promise.all([load(), auth.refresh()])
    router.push({ name: 'orders' })
  } catch (err) {
    toast(apiError(err, err?.message || 'Payment failed'), 'error')
  } finally { busy.value = null }
}

async function moveToWallet(e) {
  busy.value = e.id
  try {
    const { data } = await api.post(`/draw-entries/${e.id}/credit`)
    toast(data.message)
    await Promise.all([load(), auth.refresh()])
  } catch (err) { toast(apiError(err), 'error') } finally { busy.value = null }
}
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <h1 class="mb-1 font-display text-2xl font-bold">My draws</h1>
    <p class="mb-6 text-sm text-slate-500">Your 1% bookings. Win and the product is yours — otherwise choose to buy it or move your advance to your 1% Wallet.</p>

    <!-- Participation at a glance -->
    <div v-if="summary && summary.bookings" class="card mb-5 grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
      <div>
        <p class="font-display text-2xl font-extrabold text-brand-700">{{ summary.pools_joined }}</p>
        <p class="text-xs text-slate-500">Pools joined</p>
      </div>
      <div>
        <p class="font-display text-2xl font-extrabold text-brand-700">{{ summary.products }}</p>
        <p class="text-xs text-slate-500">Products booked</p>
      </div>
      <div>
        <p class="font-display text-2xl font-extrabold text-accent-600">{{ summary.won }}</p>
        <p class="text-xs text-slate-500">Won</p>
      </div>
      <div>
        <p class="font-display text-2xl font-extrabold text-slate-700">{{ money(summary.total_advanced) }}</p>
        <p class="text-xs text-slate-500">Total advanced</p>
      </div>
      <div v-if="summary.awaiting_choice" class="col-span-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 sm:col-span-4">
        <strong>{{ summary.awaiting_choice }}</strong> booking{{ summary.awaiting_choice > 1 ? 's need' : ' needs' }} your choice — buy at the balance, or move the advance to your 1% Wallet.
      </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <button
        type="button"
        class="chip border text-xs"
        :class="wonOnly ? 'border-accent-500 bg-accent-500 text-white' : 'border-slate-200 bg-white text-slate-500'"
        @click="wonOnly = !wonOnly"
      >
        🏆 Wins only
      </button>
      <PeriodFilter v-model="period" />
    </div>

    <div v-if="loading" class="card h-40 animate-pulse bg-slate-50" />
    <div v-else-if="!entries.length" class="card p-10 text-center text-slate-500">
      <template v-if="period.from || wonOnly">Nothing in this period.</template>
      <template v-else>No bookings yet. <RouterLink to="/shop?mode=draw" class="text-brand-700">Explore 1% draws →</RouterLink></template>
    </div>

    <div v-else class="space-y-4">
      <div v-for="e in entries" :key="e.id" class="card p-4">
        <div class="flex gap-4">
          <div class="h-20 w-20 flex-none"><ProductImage :src="e.product?.image" :name="e.product?.name || ''" /></div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <RouterLink :to="{ name: 'product', params: { slug: e.product?.slug } }" class="font-semibold hover:text-brand-700">{{ e.product?.name }}</RouterLink>
              <span class="chip" :class="badge[e.status]?.[1]">{{ badge[e.status]?.[0] || e.status }}</span>
              <span v-if="e.awaiting_claim" class="chip animate-pulse bg-amber-100 text-amber-800">Claim pending</span>
            </div>
            <p class="mt-0.5 text-sm text-slate-500">
              Advance paid {{ money(e.advance) }} · {{ e.pool?.club }} · pool {{ e.pool?.filled }}/{{ e.pool?.size }}
            </p>

            <!-- Winner: nothing ships until the prize is claimed -->
            <div v-if="e.awaiting_claim" class="mt-3 rounded-xl bg-accent-500/10 p-3">
              <p class="mb-2 text-sm text-accent-900">
                The product is yours — your {{ money(e.advance) }} covered it. Confirm delivery and settle
                the TDS on the prize value to get it dispatched.
              </p>
              <RouterLink :to="{ name: 'claim', params: { entry: e.id } }" class="btn-primary block w-full text-center">
                🏆 Claim prize
              </RouterLink>
            </div>
            <p v-else-if="e.status === 'won'" class="mt-2 text-sm text-brand-700">
              Claimed — your {{ money(e.advance) }} covered it. It's on its way to you.
            </p>

            <!-- Non-winner: the two options, same as My Orders -->
            <div v-else-if="e.awaiting_choice" class="mt-3 rounded-xl bg-amber-50/70 p-3">
              <p class="mb-3 text-sm text-slate-700">
                <template v-if="e.won_other_in_pool">
                  🎉 You won a different item in this pool. This seat is still yours to decide on —
                </template>
                <template v-else>
                  You didn't win this pool. Your {{ money(e.advance) }} is safe —
                </template>
                pick one by <strong>{{ deadline(e.choice_deadline_at) }}</strong>, or we'll move it to your 1% Wallet automatically.
              </p>
              <DrawChoiceButtons
                :entry="e"
                :busy="busy === e.id"
                :in-cart="cart.has('balance', e.id)"
                allow-cart
                @pay="payBalance(e)"
                @wallet="moveToWallet(e)"
                @cart="addToCart(e)"
              />
            </div>

            <p v-else-if="e.status === 'converted'" class="mt-2 text-sm text-slate-500">You paid the balance — see your orders.</p>
            <p v-else-if="e.status === 'credited'" class="mt-2 text-sm text-slate-500">{{ money(e.advance) }} was moved to your 1% Wallet.</p>
            <p v-else-if="e.status === 'active'" class="mt-2 text-sm text-slate-500">Pool is still filling. Draw happens at {{ e.pool?.size }} seats — odds 1 in {{ e.pool?.size }}.</p>
          </div>
        </div>
      </div>

      <button v-if="page.current < page.last" class="btn-ghost w-full" :disabled="loadingMore" @click="more">
        {{ loadingMore ? 'Loading…' : 'Load more' }}
      </button>
    </div>
  </div>
</template>
