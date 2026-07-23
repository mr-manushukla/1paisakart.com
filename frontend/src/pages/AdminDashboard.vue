<script setup>
import { ref, onMounted } from 'vue'
import api from '../lib/api'
import { money } from '../lib/money'
import { toast, apiError } from '../lib/toast'

const tab = ref('categories')
const categories = ref([])
const vendors = ref([])
const batches = ref([])
const fee = ref(0)
const coupons = ref([])
const cBlank = () => ({ code: '', type: 'percent', value: 10, minOrderR: '', maxDiscountR: '', usage_limit: '', per_user_limit: 1, ends_at: '' })
const cForm = ref(cBlank())

const newCat = ref('')
const vendorForm = ref({ name: '', email: '', password: '', shop_name: '' })

// Customer pool-participation lookup
const customers = ref([])
const custQ = ref('')
const selected = ref(null)     // { customer, summary, entries }
const loadingCust = ref(false)

const statusLabel = {
  active: 'In the pool', won: 'Won', lost_pending: 'Awaiting choice',
  converted: 'Bought at balance', credited: 'Moved to wallet', refunded: 'Refunded',
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

onMounted(() => Promise.all([loadCats(), loadVendors(), loadBatches(), loadSettings(), loadCustomers(), loadCoupons()]))

async function addCat() {
  if (!newCat.value) return
  try { await api.post('/admin/categories', { name: newCat.value }); newCat.value = ''; await loadCats(); toast('Category added') }
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
</script>

<template>
  <div>
    <h1 class="mb-6 font-display text-2xl font-bold">Admin dashboard</h1>

    <div class="mb-6 flex gap-2 border-b border-slate-100">
      <button v-for="t in ['categories', 'vendors', 'customers', 'coupons', 'batches', 'settings']" :key="t" class="px-4 py-2 text-sm font-medium capitalize" :class="tab === t ? 'border-b-2 border-brand-600 text-brand-700' : 'text-slate-500'" @click="tab = t">{{ t }}</button>
    </div>

    <!-- Categories -->
    <div v-show="tab === 'categories'" class="mx-auto max-w-xl">
      <form class="mb-4 flex gap-2" @submit.prevent="addCat">
        <input v-model="newCat" class="input" placeholder="New category name" />
        <button class="btn-primary">Add</button>
      </form>
      <div class="card divide-y divide-slate-100">
        <div v-for="c in categories" :key="c.id" class="flex items-center justify-between px-4 py-3">
          <span>{{ c.name }} <span class="text-sm text-slate-400">· {{ c.products_count }} products</span></span>
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
            <p class="text-sm text-slate-500">{{ selected.customer.email }} · wallet {{ money(selected.customer.wallet_balance) }}</p>
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
      </div>
      <p v-if="!batches.length" class="card p-8 text-center text-slate-500">No batches.</p>
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
