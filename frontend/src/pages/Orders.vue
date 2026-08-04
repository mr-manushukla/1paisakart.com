<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'
import { payAndFulfil } from '../lib/razorpay'
import DrawChoiceButtons from '../components/DrawChoiceButtons.vue'
import { useCartStore } from '../stores/cart'

const cart = useCartStore()
const auth = useAuthStore()
const orders = ref([])
const draws = ref([])
const loading = ref(true)
const busy = ref(null)      // entry id whose choice is being processed
const filter = ref('all')   // all | purchases | draws | winnings

const statusChip = {
  paid: 'bg-brand-50 text-brand-700',
  fulfilled: 'bg-brand-100 text-brand-800',
  pending: 'bg-amber-50 text-amber-700',
  cancelled: 'bg-rose-50 text-rose-600',
}
const drawChip = {
  active: ['In the pool', 'bg-brand-50 text-brand-700'],
  won: ['🎉 Won', 'bg-accent-500 text-white'],
  lost_pending: ['Choose an option', 'bg-amber-50 text-amber-700'],
  converted: ['Bought at balance', 'bg-brand-100 text-brand-800'],
  credited: ['Moved to 1% Wallet', 'bg-slate-100 text-slate-600'],
  refunded: ['Refunded', 'bg-slate-100 text-slate-600'],
}

async function load() {
  // Wins are fetched on their own: the main list is paged, and a heavy booker's
  // win can sit several pages deep, which is why the Winnings tab looked empty.
  const [o, d, w] = await Promise.all([
    api.get('/orders'),
    api.get('/my-draws'),
    api.get('/my-draws', { params: { status: 'won' } }),
  ])
  orders.value = o.data.data
  draws.value = d.data.data
  wins.value = w.data.data
}
onMounted(async () => {
  try { await load() } finally { loading.value = false }
})

// The two post-draw options, actionable right here instead of bouncing the
// customer to My Draws. Same calls that page makes.
async function payBalance(entry) {
  busy.value = entry.id
  try {
    const result = await payAndFulfil({ intent: 'balance', entry_id: entry.id, apply_wallet: true })
    if (!result) return toast('Payment cancelled', 'error')
    toast('Payment successful — the product is yours 🎉')
    await Promise.all([load(), auth.refresh()])
  } catch (e) {
    toast(apiError(e, e?.message || 'Payment failed'), 'error')
  } finally { busy.value = null }
}

/** Queue this booking's 99% balance so several can be paid in one checkout. */
function addToCart(entry) {
  cart.addBalance(entry)
  toast(`${entry.product?.name ?? 'Booking'} added to cart — pay for several at once`)
}

async function moveToWallet(entry) {
  busy.value = entry.id
  try {
    const { data } = await api.post(`/draw-entries/${entry.id}/credit`)
    toast(data.message)
    await Promise.all([load(), auth.refresh()])
  } catch (e) { toast(apiError(e), 'error') } finally { busy.value = null }
}

/** Pools this customer actually won — fetched complete, never page-limited. */
const wins = ref([])

/** Purchases and 1% bookings in one timeline, newest first. */
const rows = computed(() => {
  const list = []
  if (filter.value === 'winnings') {
    wins.value.forEach((d) => list.push({ kind: 'draw', at: d.created_at, data: d }))
    return list.sort((a, b) => new Date(b.at) - new Date(a.at))
  }
  if (filter.value !== 'draws') {
    orders.value.forEach((o) => list.push({ kind: 'order', at: o.created_at, data: o }))
  }
  if (filter.value !== 'purchases') {
    draws.value.forEach((d) => list.push({ kind: 'draw', at: d.created_at, data: d }))
  }
  return list.sort((a, b) => new Date(b.at) - new Date(a.at))
})

const needsChoice = computed(() => draws.value.filter((d) => d.awaiting_choice).length)
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <h1 class="mb-1 font-display text-2xl font-bold">My orders</h1>
    <p class="mb-4 text-sm text-slate-500">Everything you've bought and every 1% booking you've made.</p>

    <div class="mb-4 flex gap-2">
      <button
        v-for="f in [['all', 'All'], ['purchases', 'Purchases'], ['draws', '1% bookings'], ['winnings', '🏆 Winnings']]"
        :key="f[0]"
        class="chip border"
        :class="filter === f[0] ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 bg-white text-slate-500'"
        @click="filter = f[0]"
      >
        {{ f[1] }}<span v-if="f[0] === 'winnings' && wins.length" class="ml-1 font-bold">{{ wins.length }}</span>
      </button>
    </div>

    <RouterLink
      v-if="needsChoice"
      to="/my-draws"
      class="mb-4 block rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 hover:bg-amber-100"
    >
      <strong>{{ needsChoice }}</strong> booking{{ needsChoice > 1 ? 's need' : ' needs' }} your choice — buy at the balance or move the advance to your 1% Wallet →
    </RouterLink>

    <div v-if="loading" class="card h-40 animate-pulse bg-slate-50" />
    <div v-else-if="!rows.length && filter === 'winnings'" class="card p-10 text-center text-slate-500">
      🏆 No wins yet — every pool you join is a 1 in 100 shot.
      <RouterLink to="/shop?mode=draw" class="text-brand-700">Join a pool →</RouterLink>
    </div>
    <div v-else-if="!rows.length" class="card p-10 text-center text-slate-500">
      Nothing here yet. <RouterLink to="/shop" class="text-brand-700">Shop now →</RouterLink>
    </div>

    <div v-else class="space-y-4">
      <div v-for="row in rows" :key="row.kind + row.data.id" class="card p-4">
        <!-- A normal purchase / draw-win order -->
        <template v-if="row.kind === 'order'">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="font-semibold">Order #{{ row.data.id }}</span>
              <span v-if="row.data.source === 'draw_win'" class="chip bg-accent-500 text-white">🎉 Draw win</span>
            </div>
            <span class="chip capitalize" :class="statusChip[row.data.status]">{{ row.data.status }}</span>
          </div>
          <div class="mt-2 divide-y divide-slate-50 text-sm">
            <div v-for="(it, idx) in row.data.items" :key="idx" class="flex justify-between py-1">
              <span class="text-slate-600">{{ it.product }} <span class="text-slate-400">× {{ it.qty }}</span></span>
              <span>{{ money(it.line_total) }}</span>
            </div>
          </div>
          <div class="mt-2 flex justify-end gap-6 border-t border-slate-100 pt-2 text-sm">
            <span v-if="row.data.wallet_applied" class="text-brand-700">1% Wallet − {{ money(row.data.wallet_applied) }}</span>
            <span class="font-semibold">Paid {{ money(row.data.payable) }}</span>
          </div>
        </template>

        <!-- A 1% participation -->
        <template v-else>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="font-semibold">1% booking</span>
              <span class="chip" :class="drawChip[row.data.status]?.[1]">{{ drawChip[row.data.status]?.[0] || row.data.status }}</span>
            </div>
            <span class="text-sm font-semibold">{{ money(row.data.advance) }}</span>
          </div>
          <div class="mt-1 flex flex-wrap items-baseline justify-between gap-x-3">
            <p class="text-sm text-slate-600">{{ row.data.product?.name }}</p>
            <!-- The product's own price, not just the pool it sits in. -->
            <p v-if="row.data.product?.listed_price" class="text-sm">
              <span class="text-slate-400">Product price</span>
              <span class="ml-1 font-semibold text-slate-700">{{ money(row.data.product.listed_price) }}</span>
            </p>
          </div>
          <p class="text-xs text-slate-500">
            {{ row.data.pool?.club }} · pool #{{ row.data.pool?.batch_no }} · {{ row.data.pool?.filled }}/{{ row.data.pool?.size }} seats
          </p>

          <!-- Non-winner: both options, side by side and equally weighted -->
          <div v-if="row.data.awaiting_choice" class="mt-3 border-t border-slate-100 pt-3">
            <DrawChoiceButtons
              :entry="row.data"
              :busy="busy === row.data.id"
              :in-cart="cart.has('balance', row.data.id)"
              allow-cart
              @pay="payBalance(row.data)"
              @wallet="moveToWallet(row.data)"
              @cart="addToCart(row.data)"
            />
          </div>

          <div v-else class="mt-2 border-t border-slate-100 pt-2 text-xs text-slate-500">
            <template v-if="row.data.status === 'active'">Draw happens when the pool fills.</template>
            <template v-else-if="row.data.status === 'won'">Your {{ money(row.data.advance) }} covered it.</template>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
