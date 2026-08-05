<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../lib/api'
import { money } from '../lib/money'
import { toast, apiError } from '../lib/toast'
import { GRADIENTS, gradientClass, headingParts } from '../lib/gradients'

const tab = ref('categories')
const categories = ref([])
const vendors = ref([])
const batches = ref([])
const fee = ref(0)
const coupons = ref([])
const cBlank = () => ({ code: '', type: 'percent', value: 10, minOrderR: '', maxDiscountR: '', usage_limit: '', per_user_limit: 1, ends_at: '' })
const cForm = ref(cBlank())

const newCat = ref('')
const newCatIcon = ref('')
const vendorForm = ref({ name: '', email: '', password: '', shop_name: '' })

// Customer pool-participation lookup
const customers = ref([])
const custQ = ref('')
const selected = ref(null)     // { customer, summary, entries }
const loadingCust = ref(false)

const statusLabel = {
  active: 'In the pool', won: 'Won', lost_pending: 'Awaiting choice',
  converted: 'Bought at balance', credited: 'Moved to 1% Wallet', refunded: 'Refunded',
}

async function loadCustomers() {
  loadingCust.value = true
  try {
    const { data } = await api.get('/admin/customers', { params: { q: custQ.value || undefined } })
    customers.value = data.data
  } catch (e) { toast(apiError(e), 'error') } finally { loadingCust.value = false }
}

async function viewCustomer(c) {
  try {
    const { data } = await api.get(`/admin/customers/${c.id}/draws`)
    selected.value = { customer: data.customer, summary: data.summary, entries: data.data }
  } catch (e) { toast(apiError(e), 'error') }
}

async function loadCats() { categories.value = (await api.get('/admin/categories')).data }
async function loadVendors() { vendors.value = (await api.get('/admin/vendors')).data }
async function loadBatches() { batches.value = (await api.get('/admin/batches')).data.data }

// ---- Purchase analytics: 1% bookings vs full payments ----
const buyTab = ref('draws')          // draws | full
const buyQ = ref('')
const buyFrom = ref('')              // inclusive, yyyy-mm-dd
const buyTo = ref('')
const buyLoading = ref(false)
const purchases = ref({ data: [], summary: {}, current_page: 1, last_page: 1 })

async function loadPurchases(page = 1) {
  buyLoading.value = true
  try {
    const { data } = await api.get(`/admin/purchases/${buyTab.value}`, {
      params: { q: buyQ.value || undefined, from: buyFrom.value || undefined, to: buyTo.value || undefined, page },
    })
    purchases.value = data
  } catch (e) { toast(apiError(e), 'error') } finally { buyLoading.value = false }
}
function switchBuyTab(t) { buyTab.value = t; purchases.value = { data: [], summary: {} }; loadPurchases() }
function clearBuyFilters() { buyQ.value = ''; buyFrom.value = ''; buyTo.value = ''; loadPurchases() }

const drawStatusLabel = {
  active: 'In the pool', won: '🏆 Won', lost_pending: 'Awaiting choice',
  converted: 'Paid the 99%', credited: 'Moved to wallet', refunded: 'Refunded',
}

// Drawn pools only, for the Winners tab.
const winnerQ = ref('')
const wonBatches = computed(() => batches.value.filter((b) => b.winner))
const filteredWinners = computed(() => {
  const q = winnerQ.value.trim().toLowerCase()
  if (!q) return wonBatches.value
  return wonBatches.value.filter((b) => [b.id, b.club, b.batch_no, b.winner?.name, b.winner?.aid, b.winner?.email, b.winner?.product_name]
    .join(' ').toLowerCase().includes(q))
})
async function loadSettings() { fee.value = (await api.get('/admin/settings')).data.platform_fee_pct }
async function loadCoupons() { coupons.value = (await api.get('/admin/coupons')).data }

async function createCoupon() {
  const f = cForm.value
  try {
    await api.post('/admin/coupons', {
      code: f.code || null, type: f.type, value: Number(f.value),
      min_order: f.minOrderR ? Math.round(Number(f.minOrderR) * 100) : 0,
      max_discount: f.maxDiscountR ? Math.round(Number(f.maxDiscountR) * 100) : null,
      usage_limit: f.usage_limit ? Number(f.usage_limit) : null,
      per_user_limit: Number(f.per_user_limit) || 1,
      ends_at: f.ends_at || null,
    })
    toast('Platform coupon created'); cForm.value = cBlank(); await loadCoupons()
  } catch (e) { toast(apiError(e), 'error') }
}
async function toggleCoupon(c) {
  try { const { data } = await api.post(`/admin/coupons/${c.id}/toggle`); toast(data.message); await loadCoupons() }
  catch (e) { toast(apiError(e), 'error') }
}
async function deleteCoupon(c) {
  if (!confirm(`Delete coupon ${c.code}?`)) return
  try { await api.delete(`/admin/coupons/${c.id}`); toast('Deleted'); await loadCoupons() }
  catch (e) { toast(apiError(e), 'error') }
}

onMounted(() => Promise.all([loadCats(), loadVendors(), loadBatches(), loadSettings(), loadCustomers(), loadCoupons(), loadSlides(), loadPurchases()]))

async function addCat() {
  if (!newCat.value) return
  try {
    await api.post('/admin/categories', { name: newCat.value, icon: newCatIcon.value || null })
    newCat.value = ''; newCatIcon.value = ''
    await loadCats(); toast('Category added')
  } catch (e) { toast(apiError(e), 'error') }
}
/** Set the emoji shown on the home tile for a category. */
async function setCatIcon(c) {
  const icon = prompt(`Emoji for “${c.name}”`, c.icon || '')
  if (icon === null) return
  try { await api.put(`/admin/categories/${c.id}`, { name: c.name, icon: icon || null }); await loadCats(); toast('Icon updated') }
  catch (e) { toast(apiError(e), 'error') }
}
async function delCat(c) {
  if (!confirm(`Delete "${c.name}"?`)) return
  try { await api.delete(`/admin/categories/${c.id}`); await loadCats(); toast('Deleted') }
  catch (e) { toast(apiError(e), 'error') }
}
async function addVendor() {
  try { await api.post('/admin/vendors', vendorForm.value); vendorForm.value = { name: '', email: '', password: '', shop_name: '' }; await loadVendors(); toast('Vendor created') }
  catch (e) { toast(apiError(e), 'error') }
}
async function cancelBatch(b) {
  if (!confirm('Cancel batch and refund all entries?')) return
  try { await api.post(`/admin/batches/${b.id}/cancel`); await loadBatches(); toast('Batch cancelled') }
  catch (e) { toast(apiError(e), 'error') }
}
async function saveFee() {
  try { await api.put('/admin/settings', { platform_fee_pct: Number(fee.value) }); toast('Saved') }
  catch (e) { toast(apiError(e), 'error') }
}

// ---- Homepage hero slides ----
const MAX_SLIDES = 8
const slides = ref([])
const slidesBusy = ref(false)
const gradientKeys = Object.keys(GRADIENTS)
const blankSlide = () => ({ eyebrow: '', heading: 'New headline with a *highlight*', text: '', cta_label: 'Shop now', cta_to: '/shop', gradient: 'green', image: '' })

async function loadSlides() { slides.value = (await api.get('/slides')).data.data }

function moveSlide(i, by) {
  const to = i + by
  if (to < 0 || to >= slides.value.length) return
  const [s] = slides.value.splice(i, 1)
  slides.value.splice(to, 0, s)
}

async function uploadSlideImage(i, e) {
  const file = e.target.files[0]
  e.target.value = '' // allow re-selecting the same file
  if (!file) return

  const fd = new FormData()
  fd.append('image', file)
  slidesBusy.value = true
  try {
    const { data } = await api.post('/admin/slides/image', fd)
    slides.value[i].image = data.url
    toast('Image uploaded — remember to save')
  } catch (err) { toast(apiError(err), 'error') } finally { slidesBusy.value = false }
}

async function saveSlides() {
  slidesBusy.value = true
  try {
    const { data } = await api.put('/admin/slides', { slides: slides.value })
    slides.value = data.data
    toast('Slides saved — the homepage is live with them now')
  } catch (e) { toast(apiError(e), 'error') } finally { slidesBusy.value = false }
}
</script>

<template>
  <div>
    <h1 class="mb-6 font-display text-2xl font-bold">Admin dashboard</h1>

    <div class="mb-6 flex gap-2 border-b border-slate-100">
      <button v-for="t in ['categories', 'vendors', 'customers', 'purchases', 'coupons', 'batches', 'winners', 'slides', 'settings']" :key="t" class="px-4 py-2 text-sm font-medium capitalize" :class="tab === t ? 'border-b-2 border-brand-600 text-brand-700' : 'text-slate-500'" @click="tab = t">{{ t }}</button>
    </div>

    <!-- Categories -->
    <div v-show="tab === 'categories'" class="mx-auto max-w-xl">
      <form class="mb-2 flex gap-2" @submit.prevent="addCat">
        <input v-model="newCatIcon" class="input w-16 text-center" maxlength="4" placeholder="🏷️" aria-label="Category icon" />
        <input v-model="newCat" class="input flex-1" placeholder="New category name" />
        <button class="btn-primary">Add</button>
      </form>
      <p class="mb-4 text-xs text-slate-500">The icon shows on the home page tile. Vendors pick these categories for their products.</p>
      <div class="card divide-y divide-slate-100">
        <div v-for="c in categories" :key="c.id" class="flex items-center justify-between px-4 py-3">
          <span class="flex items-center gap-2">
            <button class="text-xl" :title="`Change icon for ${c.name}`" @click="setCatIcon(c)">{{ c.icon || '🏷️' }}</button>
            <span>{{ c.name }} <span class="text-sm text-slate-400">· {{ c.products_count }} products</span></span>
          </span>
          <button class="text-rose-500 hover:text-rose-700" @click="delCat(c)">✕</button>
        </div>
      </div>
    </div>

    <!-- Vendors -->
    <div v-show="tab === 'vendors'" class="grid gap-6 lg:grid-cols-[320px_1fr]">
      <form class="card h-fit space-y-3 p-4" @submit.prevent="addVendor">
        <h2 class="font-semibold">Add vendor</h2>
        <input v-model="vendorForm.name" class="input" placeholder="Owner name" required />
        <input v-model="vendorForm.email" type="email" class="input" placeholder="Email" required />
        <input v-model="vendorForm.password" type="password" class="input" placeholder="Password (min 8)" required />
        <input v-model="vendorForm.shop_name" class="input" placeholder="Shop name" required />
        <button class="btn-primary w-full">Create vendor</button>
      </form>
      <div class="space-y-2">
        <div v-for="v in vendors" :key="v.id" class="card flex items-center justify-between p-3">
          <div><p class="font-semibold">{{ v.shop || '—' }}</p><p class="text-sm text-slate-500">{{ v.name }} · {{ v.email }}</p></div>
          <span class="chip bg-brand-50 text-brand-700">{{ v.products }} products</span>
        </div>
        <p v-if="!vendors.length" class="card p-8 text-center text-slate-500">No vendors yet.</p>
      </div>
    </div>

    <!-- Customers: which pools each has participated in -->
    <div v-show="tab === 'customers'" class="grid gap-6 lg:grid-cols-[360px_1fr]">
      <div class="space-y-3">
        <form class="flex gap-2" @submit.prevent="loadCustomers">
          <input v-model="custQ" class="input" placeholder="Search name or email…" />
          <button class="btn-primary">Search</button>
        </form>
        <div v-if="loadingCust" class="card h-32 animate-pulse bg-slate-50" />
        <div v-else class="space-y-2">
          <button
            v-for="c in customers"
            :key="c.id"
            class="card flex w-full items-center justify-between p-3 text-left transition hover:border-brand-300"
            :class="selected?.customer?.id === c.id ? 'border-brand-500 bg-brand-50/40' : ''"
            @click="viewCustomer(c)"
          >
            <span class="min-w-0">
              <span class="block truncate font-semibold">{{ c.name }}</span>
              <span class="block truncate text-xs text-slate-500">{{ c.email }}</span>
            </span>
            <span class="chip flex-none" :class="c.bookings ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-400'">
              {{ c.bookings }} booking{{ c.bookings === 1 ? '' : 's' }}
            </span>
          </button>
          <p v-if="!customers.length" class="card p-6 text-center text-sm text-slate-500">No customers match.</p>
        </div>
      </div>

      <div>
        <p v-if="!selected" class="card p-10 text-center text-slate-500">Select a customer to see the pools they've participated in.</p>
        <div v-else class="space-y-4">
          <div class="card p-4">
            <p class="font-display text-lg font-bold">{{ selected.customer.name }}</p>
            <p class="text-sm text-slate-500">{{ selected.customer.email }} · 1% Wallet {{ money(selected.customer.wallet_balance) }}</p>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
              <div><p class="font-display text-xl font-bold text-brand-700">{{ selected.summary.pools_joined }}</p><p class="text-xs text-slate-500">Pools joined</p></div>
              <div><p class="font-display text-xl font-bold text-brand-700">{{ selected.summary.products }}</p><p class="text-xs text-slate-500">Products</p></div>
              <div><p class="font-display text-xl font-bold text-accent-600">{{ selected.summary.won }}</p><p class="text-xs text-slate-500">Won</p></div>
              <div><p class="font-display text-xl font-bold">{{ money(selected.summary.total_advanced) }}</p><p class="text-xs text-slate-500">Advanced</p></div>
            </div>
          </div>

          <div v-if="selected.entries.length" class="card divide-y divide-slate-100">
            <div v-for="e in selected.entries" :key="e.id" class="flex items-center justify-between gap-3 px-4 py-3">
              <div class="min-w-0">
                <p class="truncate font-medium">{{ e.product?.name }}</p>
                <p class="text-xs text-slate-500">{{ e.pool?.club }} · pool #{{ e.pool?.batch_no }} · {{ e.pool?.filled }}/{{ e.pool?.size }} seats</p>
              </div>
              <div class="flex-none text-right">
                <p class="text-sm font-semibold">{{ money(e.advance) }}</p>
                <span class="chip bg-slate-100 text-slate-600">{{ statusLabel[e.status] || e.status }}</span>
              </div>
            </div>
          </div>
          <p v-else class="card p-8 text-center text-slate-500">This customer hasn't joined any pool yet.</p>
        </div>
      </div>
    </div>

    <!-- Coupons: platform-wide, plus oversight of every vendor coupon -->
    <!-- Purchases: who bought what, split by how they paid -->
    <div v-show="tab === 'purchases'" class="space-y-4">
      <div class="space-y-3">
        <div class="flex gap-2">
          <button
            class="chip border"
            :class="buyTab === 'draws' ? 'border-accent-500 bg-accent-500/10 text-accent-700' : 'border-slate-200 bg-white text-slate-500'"
            @click="switchBuyTab('draws')"
          >1% Purchases</button>
          <button
            class="chip border"
            :class="buyTab === 'full' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 bg-white text-slate-500'"
            @click="switchBuyTab('full')"
          >Full Payment Purchases</button>
        </div>
        <form class="flex flex-wrap items-center gap-2" @submit.prevent="loadPurchases()">
          <label class="flex items-center gap-1 text-xs text-slate-500">
            From
            <input v-model="buyFrom" type="date" :max="buyTo || undefined" class="input py-1 text-sm" @change="loadPurchases()" />
          </label>
          <label class="flex items-center gap-1 text-xs text-slate-500">
            To
            <input v-model="buyTo" type="date" :min="buyFrom || undefined" class="input py-1 text-sm" @change="loadPurchases()" />
          </label>
          <input v-model="buyQ" class="input max-w-xs text-sm" placeholder="Search customer or product…" />
          <button class="btn-ghost px-3 py-1.5 text-sm">Search</button>
          <button v-if="buyQ || buyFrom || buyTo" type="button" class="text-xs text-slate-500 underline" @click="clearBuyFilters">Clear</button>
        </form>
      </div>

      <!-- Headline numbers -->
      <div v-if="buyTab === 'draws'" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="card p-3"><p class="font-display text-xl font-extrabold text-accent-700">{{ purchases.summary?.bookings ?? 0 }}</p><p class="text-xs text-slate-500">1% bookings</p></div>
        <div class="card p-3"><p class="font-display text-xl font-extrabold">{{ purchases.summary?.customers ?? 0 }}</p><p class="text-xs text-slate-500">Customers</p></div>
        <div class="card p-3"><p class="font-display text-xl font-extrabold">{{ money(purchases.summary?.collected ?? 0) }}</p><p class="text-xs text-slate-500">Advances collected</p></div>
        <div class="card p-3">
          <p class="font-display text-xl font-extrabold">{{ purchases.summary?.won ?? 0 }}</p>
          <p class="text-xs text-slate-500">Won · {{ purchases.summary?.converted ?? 0 }} paid 99% · {{ purchases.summary?.credited ?? 0 }} to wallet</p>
        </div>
      </div>
      <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="card p-3"><p class="font-display text-xl font-extrabold text-brand-700">{{ purchases.summary?.orders ?? 0 }}</p><p class="text-xs text-slate-500">Full-payment orders</p></div>
        <div class="card p-3"><p class="font-display text-xl font-extrabold">{{ purchases.summary?.customers ?? 0 }}</p><p class="text-xs text-slate-500">Customers</p></div>
        <div class="card p-3"><p class="font-display text-xl font-extrabold">{{ money(purchases.summary?.revenue ?? 0) }}</p><p class="text-xs text-slate-500">Revenue taken</p></div>
        <div class="card p-3">
          <p class="font-display text-xl font-extrabold">{{ purchases.summary?.direct ?? 0 }}</p>
          <p class="text-xs text-slate-500">Direct buys · {{ purchases.summary?.from_draw ?? 0 }} after a draw</p>
        </div>
      </div>

      <div v-if="buyLoading" class="card h-40 animate-pulse bg-slate-50" />

      <!-- 1% bookings -->
      <div v-else-if="buyTab === 'draws'" class="card divide-y divide-slate-100">
        <div v-for="r in purchases.data" :key="r.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
          <div class="min-w-0">
            <p class="font-semibold">{{ r.user.name }} <span class="text-xs font-normal text-slate-400">{{ r.user.email }}</span></p>
            <p class="truncate text-sm text-slate-600">{{ r.product }}</p>
            <p class="text-xs text-slate-400">{{ r.club }} · pool #{{ r.pool_no }} · {{ new Date(r.at).toLocaleDateString('en-IN') }}</p>
          </div>
          <div class="text-right">
            <p class="font-semibold text-accent-700">{{ money(r.advance) }}</p>
            <p class="text-xs text-slate-400">of {{ money(r.product_price) }}</p>
            <span class="chip mt-1 bg-slate-100 text-slate-600">{{ drawStatusLabel[r.status] || r.status }}</span>
          </div>
        </div>
        <p v-if="!purchases.data?.length" class="p-8 text-center text-slate-500">No 1% purchases yet.</p>
      </div>

      <!-- Full payments -->
      <div v-else class="card divide-y divide-slate-100">
        <div v-for="r in purchases.data" :key="r.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
          <div class="min-w-0">
            <p class="font-semibold">
              {{ r.user.name }} <span class="text-xs font-normal text-slate-400">{{ r.user.email }}</span>
              <span class="chip ml-1" :class="r.origin === 'draw_99' ? 'bg-accent-500/10 text-accent-700' : 'bg-brand-50 text-brand-700'">
                {{ r.origin === 'draw_99' ? 'Paid 99% after draw' : 'Direct buy' }}
              </span>
            </p>
            <p v-for="(it, i) in r.items" :key="i" class="truncate text-sm text-slate-600">
              {{ it.product }} <span class="text-slate-400">× {{ it.qty }}</span>
            </p>
            <p class="text-xs text-slate-400">Order #{{ r.id }} · {{ new Date(r.at).toLocaleDateString('en-IN') }}</p>
          </div>
          <div class="text-right">
            <p class="font-semibold text-brand-700">{{ money(r.payable) }}</p>
            <p v-if="r.wallet_applied" class="text-xs text-slate-400">1% Wallet − {{ money(r.wallet_applied) }}</p>
            <p v-if="r.discount" class="text-xs text-green-700">Coupon − {{ money(r.discount) }}</p>
          </div>
        </div>
        <p v-if="!purchases.data?.length" class="p-8 text-center text-slate-500">No full-payment purchases yet.</p>
      </div>

      <div v-if="purchases.last_page > 1" class="flex items-center justify-center gap-2">
        <button class="btn-ghost px-3 py-1.5 text-sm" :disabled="purchases.current_page <= 1" @click="loadPurchases(purchases.current_page - 1)">Prev</button>
        <span class="text-sm text-slate-500">Page {{ purchases.current_page }} of {{ purchases.last_page }}</span>
        <button class="btn-ghost px-3 py-1.5 text-sm" :disabled="purchases.current_page >= purchases.last_page" @click="loadPurchases(purchases.current_page + 1)">Next</button>
      </div>
    </div>

    <div v-show="tab === 'coupons'" class="grid gap-6 lg:grid-cols-[320px_1fr]">
      <form class="card h-fit space-y-3 p-4" @submit.prevent="createCoupon">
        <h2 class="font-semibold">New platform coupon</h2>
        <p class="text-xs text-slate-500">Valid across every vendor's products.</p>
        <input v-model="cForm.code" class="input uppercase" placeholder="CODE (optional)" />
        <div class="flex gap-2">
          <select v-model="cForm.type" class="input flex-1">
            <option value="percent">% off</option>
            <option value="fixed">₹ off</option>
          </select>
          <label class="flex-1 text-xs text-slate-500">
            {{ cForm.type === 'percent' ? 'Percent' : 'Amount (₹)' }}
            <input v-model="cForm.value" type="number" min="1" class="input" required />
          </label>
        </div>
        <div class="flex gap-2">
          <label class="flex-1 text-xs text-slate-500">Min order (₹)<input v-model="cForm.minOrderR" type="number" min="0" class="input" placeholder="0" /></label>
          <label v-if="cForm.type === 'percent'" class="flex-1 text-xs text-slate-500">Max discount (₹)<input v-model="cForm.maxDiscountR" type="number" min="1" class="input" placeholder="no cap" /></label>
        </div>
        <div class="flex gap-2">
          <label class="flex-1 text-xs text-slate-500">Total uses<input v-model="cForm.usage_limit" type="number" min="1" class="input" placeholder="unlimited" /></label>
          <label class="flex-1 text-xs text-slate-500">Per customer<input v-model="cForm.per_user_limit" type="number" min="1" class="input" /></label>
        </div>
        <label class="block text-xs text-slate-500">Expires<input v-model="cForm.ends_at" type="date" class="input" /></label>
        <button class="btn-primary w-full">Create</button>
      </form>

      <div class="space-y-2">
        <div v-for="c in coupons" :key="c.id" class="card flex items-center justify-between p-3">
          <div class="min-w-0">
            <p class="font-semibold">
              {{ c.code }}
              <span class="ml-1 font-normal text-slate-500">{{ c.type === 'percent' ? c.value + '% off' : money(c.value) + ' off' }}</span>
            </p>
            <p class="text-xs text-slate-500">
              {{ c.scope }} · used {{ c.used_count }}<template v-if="c.usage_limit">/{{ c.usage_limit }}</template>
              <template v-if="c.min_order"> · min {{ money(c.min_order) }}</template>
            </p>
          </div>
          <div class="flex flex-none items-center gap-2">
            <span class="chip" :class="c.live ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500'">{{ c.live ? 'Live' : 'Inactive' }}</span>
            <button class="btn-ghost px-2 py-1 text-xs" @click="toggleCoupon(c)">{{ c.active ? 'Disable' : 'Enable' }}</button>
            <button class="px-2 text-rose-500 hover:text-rose-700" @click="deleteCoupon(c)">✕</button>
          </div>
        </div>
        <p v-if="!coupons.length" class="card p-8 text-center text-slate-500">No coupons yet.</p>
      </div>
    </div>

    <!-- Batches -->
    <div v-show="tab === 'batches'" class="space-y-2">
      <div v-for="b in batches" :key="b.id" class="card flex items-center justify-between p-3">
        <div>
          <p class="font-semibold">{{ b.club }} · pool #{{ b.batch_no }}</p>
          <p class="text-sm text-slate-500">{{ b.filled }}/{{ b.size }} seats · pooled {{ money(b.pooled) }} · <span class="capitalize">{{ b.status }}</span></p>
        </div>
        <button v-if="b.status === 'open'" class="btn-ghost px-3 py-1.5 text-sm text-rose-600" @click="cancelBatch(b)">Cancel</button>
        <button v-else-if="b.winner" class="chip bg-accent-500 text-white" @click="tab = 'winners'">🎉 Winner drawn</button>
      </div>
      <p v-if="!batches.length" class="card p-8 text-center text-slate-500">No batches.</p>
    </div>

    <!-- Winners: full details for every drawn pool, for shipping the prize -->
    <div v-show="tab === 'winners'" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-500">{{ wonBatches.length }} winner{{ wonBatches.length === 1 ? '' : 's' }} drawn.</p>
        <input v-model="winnerQ" class="input max-w-xs text-sm" placeholder="Search winner, product, pool…" />
      </div>

      <div v-for="b in filteredWinners" :key="b.id" class="card grid gap-4 border-l-4 border-accent-500 p-4 sm:grid-cols-2">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pool</p>
          <dl class="mt-1 space-y-0.5 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Pool ID</dt><dd class="font-medium">#{{ b.id }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Price band</dt><dd class="font-medium">{{ b.club }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Draw no.</dt><dd class="font-medium">Draw-{{ b.batch_no }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Total collected</dt><dd class="font-medium">{{ money(b.pooled) }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Drawn on</dt><dd class="font-medium">{{ b.drawn_at ? new Date(b.drawn_at).toLocaleString('en-IN') : '—' }}</dd></div>
          </dl>
          <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Winning product</p>
          <dl class="mt-1 space-y-0.5 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Name</dt><dd class="text-right font-medium">{{ b.winner.product_name }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Value</dt><dd class="font-medium">{{ money(b.winner.product_value) }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Advance paid</dt><dd class="font-medium">{{ money(b.winner.advance_paid) }}</dd></div>
          </dl>
        </div>
        <div class="rounded-xl bg-accent-500/5 p-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-accent-600">🏆 Winner</p>
          <dl class="mt-1 space-y-0.5 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Name</dt><dd class="font-medium">{{ b.winner.name }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">AID</dt><dd class="font-medium">{{ b.winner.aid }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="text-right font-medium">{{ b.winner.email || '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Contact</dt><dd class="font-medium">{{ b.winner.phone || '— not provided' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Address</dt><dd class="max-w-[60%] text-right font-medium">{{ b.winner.address || '— not provided' }}</dd></div>
          </dl>
        </div>
      </div>
      <p v-if="!wonBatches.length" class="card p-8 text-center text-slate-500">No winners yet — they appear here the moment a pool fills and draws.</p>
      <p v-else-if="!filteredWinners.length" class="card p-8 text-center text-slate-500">No winners match “{{ winnerQ }}”.</p>
    </div>

    <!-- Homepage slider -->
    <div v-show="tab === 'slides'" class="mx-auto max-w-3xl">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-500">
          Homepage hero slides · {{ slides.length }} / {{ MAX_SLIDES }}. Wrap words in <code class="rounded bg-slate-100 px-1">*asterisks*</code> to highlight them.
        </p>
        <div class="flex gap-2">
          <button class="btn-ghost px-3 py-1.5 text-sm" :disabled="slides.length >= MAX_SLIDES" @click="slides.push(blankSlide())">+ Add slide</button>
          <button class="btn-primary px-4 py-1.5 text-sm" :disabled="slidesBusy" @click="saveSlides">{{ slidesBusy ? 'Saving…' : 'Save slides' }}</button>
        </div>
      </div>

      <div v-for="(s, i) in slides" :key="i" class="card mb-4 overflow-hidden">
        <!-- Live preview of exactly what the homepage will render -->
        <div class="bg-gradient-to-br px-5 py-6 text-white" :class="gradientClass(s.gradient)">
          <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
              <span v-if="s.eyebrow" class="chip bg-white/15 text-white">{{ s.eyebrow }}</span>
              <p class="mt-2 font-display text-2xl font-extrabold leading-tight">
                <template v-for="(part, pi) in headingParts(s.heading)" :key="pi">
                  <span v-if="pi % 2" class="text-accent-400">{{ part }}</span>
                  <template v-else>{{ part }}</template>
                </template>
              </p>
              <p v-if="s.text" class="mt-1 line-clamp-2 text-sm text-white/85">{{ s.text }}</p>
            </div>
            <img v-if="s.image" :src="s.image" alt="" class="hidden h-20 w-20 flex-none rounded-xl object-cover ring-2 ring-white/20 sm:block" />
          </div>
        </div>

        <div class="space-y-3 p-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Slide {{ i + 1 }}</span>
            <div class="flex gap-1">
              <button class="btn-ghost px-2 py-1 text-sm" :disabled="i === 0" title="Move up" @click="moveSlide(i, -1)">↑</button>
              <button class="btn-ghost px-2 py-1 text-sm" :disabled="i === slides.length - 1" title="Move down" @click="moveSlide(i, 1)">↓</button>
              <button class="btn-ghost px-2 py-1 text-sm text-rose-600" title="Remove slide" @click="slides.splice(i, 1)">Remove</button>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">Eyebrow <span class="text-slate-400">(small label)</span>
              <input v-model="s.eyebrow" maxlength="60" class="input mt-1" placeholder="The 1% draw" />
            </label>
            <label class="block text-sm">Background
              <select v-model="s.gradient" class="input mt-1 capitalize">
                <option v-for="g in gradientKeys" :key="g" :value="g">{{ g }}</option>
              </select>
            </label>
          </div>

          <label class="block text-sm">Headline
            <input v-model="s.heading" maxlength="120" class="input mt-1" placeholder="Pay just *1%*. Win the whole thing." />
          </label>

          <label class="block text-sm">Body text
            <textarea v-model="s.text" maxlength="300" rows="2" class="input mt-1" placeholder="One or two sentences." />
          </label>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">Button label
              <input v-model="s.cta_label" maxlength="40" class="input mt-1" placeholder="Shop all products" />
            </label>
            <label class="block text-sm">Button link <span class="text-slate-400">(path on this site)</span>
              <input v-model="s.cta_to" maxlength="200" class="input mt-1" placeholder="/shop" />
            </label>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <img v-if="s.image" :src="s.image" alt="" class="h-14 w-14 flex-none rounded-lg border border-slate-200 object-cover" />
            <label class="flex-1">
              <span class="sr-only">Upload slide image</span>
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                :disabled="slidesBusy"
                class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-700 disabled:opacity-50"
                @change="uploadSlideImage(i, $event)"
              />
            </label>
            <button v-if="s.image" class="btn-ghost px-2 py-1 text-sm text-rose-600" @click="s.image = ''">Clear image</button>
          </div>
          <p class="text-xs text-slate-400">JPG, PNG or WebP · up to 4 MB · shown on large screens only.</p>
        </div>
      </div>

      <p v-if="!slides.length" class="card p-8 text-center text-slate-500">
        No slides — the homepage hero is hidden. Add one to bring it back.
      </p>
    </div>

    <!-- Settings -->
    <div v-show="tab === 'settings'" class="mx-auto max-w-md">
      <form class="card space-y-3 p-5" @submit.prevent="saveFee">
        <h2 class="font-semibold">Platform fee</h2>
        <p class="text-sm text-slate-500">Each product's listed price = vendor payout + this fee. The 100×1% pool covers the listed price.</p>
        <label class="block text-sm">Platform fee (%)
          <input v-model="fee" type="number" min="0" max="100" step="0.1" class="input mt-1" />
        </label>
        <button class="btn-primary w-full">Save</button>
      </form>
    </div>
  </div>
</template>
