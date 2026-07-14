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

const newCat = ref('')
const vendorForm = ref({ name: '', email: '', password: '', shop_name: '' })

async function loadCats() { categories.value = (await api.get('/admin/categories')).data }
async function loadVendors() { vendors.value = (await api.get('/admin/vendors')).data }
async function loadBatches() { batches.value = (await api.get('/admin/batches')).data.data }
async function loadSettings() { fee.value = (await api.get('/admin/settings')).data.platform_fee_pct }

onMounted(() => Promise.all([loadCats(), loadVendors(), loadBatches(), loadSettings()]))

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
      <button v-for="t in ['categories', 'vendors', 'batches', 'settings']" :key="t" class="px-4 py-2 text-sm font-medium capitalize" :class="tab === t ? 'border-b-2 border-brand-600 text-brand-700' : 'text-slate-500'" @click="tab = t">{{ t }}</button>
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

    <!-- Batches -->
    <div v-show="tab === 'batches'" class="space-y-2">
      <div v-for="b in batches" :key="b.id" class="card flex items-center justify-between p-3">
        <div>
          <p class="font-semibold">{{ b.product }} · batch #{{ b.batch_no }}</p>
          <p class="text-sm text-slate-500">{{ b.filled }}/{{ b.size }} · entry {{ money(b.entry_price) }} · <span class="capitalize">{{ b.status }}</span></p>
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
