<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'
import ProductImage from '../components/ProductImage.vue'

const auth = useAuthStore()
const router = useRouter()
const entries = ref([])
const loading = ref(true)
const busy = ref(null)

const badge = {
  active: ['In the pool', 'bg-brand-50 text-brand-700'],
  won: ['🎉 You won!', 'bg-accent-500 text-white'],
  lost_pending: ['Choose an option', 'bg-amber-50 text-amber-700'],
  converted: ['Purchased', 'bg-brand-100 text-brand-800'],
  credited: ['Moved to wallet', 'bg-slate-100 text-slate-600'],
  refunded: ['Refunded', 'bg-slate-100 text-slate-600'],
}

async function load() {
  const { data } = await api.get('/my-draws')
  entries.value = data.data
}
onMounted(async () => { try { await load() } finally { loading.value = false } })

const deadline = (iso) => new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })

async function payBalance(e) {
  busy.value = e.id
  try {
    const { data } = await api.post(`/draw-entries/${e.id}/purchase`, { apply_wallet: true })
    toast(`Order #${data.data.id} placed 🎉`)
    await Promise.all([load(), auth.refresh()])
    router.push({ name: 'orders' })
  } catch (err) { toast(apiError(err), 'error') } finally { busy.value = null }
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
    <p class="mb-6 text-sm text-slate-500">Your 1% bookings. Win and the product is yours — otherwise choose to buy it or move your advance to your wallet.</p>

    <div v-if="loading" class="card h-40 animate-pulse bg-slate-50" />
    <div v-else-if="!entries.length" class="card p-10 text-center text-slate-500">
      No bookings yet. <RouterLink to="/shop?mode=draw" class="text-brand-700">Explore 1% draws →</RouterLink>
    </div>

    <div v-else class="space-y-4">
      <div v-for="e in entries" :key="e.id" class="card p-4">
        <div class="flex gap-4">
          <div class="h-20 w-20 flex-none"><ProductImage :src="e.product?.image" :name="e.product?.name || ''" /></div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <RouterLink :to="{ name: 'product', params: { slug: e.product?.slug } }" class="font-semibold hover:text-brand-700">{{ e.product?.name }}</RouterLink>
              <span class="chip" :class="badge[e.status]?.[1]">{{ badge[e.status]?.[0] || e.status }}</span>
            </div>
            <p class="mt-0.5 text-sm text-slate-500">
              Advance paid {{ money(e.advance) }} · {{ e.pool?.club }} · pool {{ e.pool?.filled }}/{{ e.pool?.size }}
            </p>

            <!-- Winner -->
            <p v-if="e.status === 'won'" class="mt-2 text-sm text-brand-700">
              The product is yours — your {{ money(e.advance) }} covered it. Government taxes on the prize value may apply.
            </p>

            <!-- Non-winner: the two options -->
            <div v-else-if="e.awaiting_choice" class="mt-3 rounded-xl bg-amber-50/70 p-3">
              <p class="text-sm text-slate-700">
                You didn't win this pool. Your {{ money(e.advance) }} is safe — pick one by
                <strong>{{ deadline(e.choice_deadline_at) }}</strong>, or we'll move it to your wallet automatically.
              </p>
              <div class="mt-3 flex flex-wrap gap-2">
                <button class="btn-primary" :disabled="busy === e.id" @click="payBalance(e)">
                  Buy it — pay {{ money(e.balance_due) }}
                </button>
                <button class="btn-ghost" :disabled="busy === e.id" @click="moveToWallet(e)">
                  Move {{ money(e.advance) }} to wallet
                </button>
              </div>
            </div>

            <p v-else-if="e.status === 'converted'" class="mt-2 text-sm text-slate-500">You paid the balance — see your orders.</p>
            <p v-else-if="e.status === 'credited'" class="mt-2 text-sm text-slate-500">{{ money(e.advance) }} was moved to your wallet.</p>
            <p v-else-if="e.status === 'active'" class="mt-2 text-sm text-slate-500">Pool is still filling. Draw happens at {{ e.pool?.size }} seats — odds 1 in {{ e.pool?.size }}.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
