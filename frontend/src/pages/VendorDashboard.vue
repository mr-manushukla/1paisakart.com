<script setup>
import { ref, onMounted } from 'vue'
import api from '../lib/api'
import { money } from '../lib/money'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'

const auth = useAuthStore()
const tab = ref('products')

const products = ref([])
const orders = ref([])
const batches = ref([])
const categories = ref([])

const blank = () => ({ id: null, name: '', category_id: '', description: '', priceR: '', stock: 0, allow_full_buy: true, allow_draw: false, status: 'active' })
const form = ref(blank())
const saving = ref(false)

async function loadProducts() { products.value = (await api.get('/vendor/products')).data.data }
async function loadOrders() { orders.value = (await api.get('/vendor/orders')).data.data }
async function loadBatches() { batches.value = (await api.get('/vendor/batches')).data.data }

onMounted(async () => {
  categories.value = (await api.get('/categories')).data
  await Promise.all([loadProducts(), loadOrders(), loadBatches()])
})

function edit(p) {
  form.value = {
    id: p.id, name: p.name, category_id: p.category?.slug ? categories.value.find((c) => c.slug === p.category.slug)?.id : '',
    description: p.description || '', priceR: p.listed_price / 100, stock: p.stock,
    allow_full_buy: p.allow_full_buy, allow_draw: p.allow_draw, status: 'active',
  }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
function reset() { form.value = blank() }

async function save() {
  saving.value = true
  const payload = {
    name: form.value.name,
    category_id: form.value.category_id || null,
    description: form.value.description,
    listed_price: Math.round(Number(form.value.priceR) * 100),
    stock: Number(form.value.stock),
    allow_full_buy: form.value.allow_full_buy,
    allow_draw: form.value.allow_draw,
    status: form.value.status,
  }
  try {
    if (form.value.id) await api.put(`/vendor/products/${form.value.id}`, payload)
    else await api.post('/vendor/products', payload)
    toast('Product saved')
    reset()
    await Promise.all([loadProducts(), loadBatches()])
  } catch (e) { toast(apiError(e), 'error') } finally { saving.value = false }
}

async function remove(p) {
  if (!confirm(`Delete "${p.name}"?`)) return
  try { await api.delete(`/vendor/products/${p.id}`); toast('Deleted'); await loadProducts() }
  catch (e) { toast(apiError(e), 'error') }
}
async function cancelBatch(b) {
  if (!confirm('Cancel this batch and refund all entries?')) return
  try { await api.post(`/vendor/batches/${b.id}/cancel`); toast('Batch cancelled'); await loadBatches() }
  catch (e) { toast(apiError(e), 'error') }
}
</script>

<template>
  <div>
    <h1 class="mb-1 font-display text-2xl font-bold">Vendor dashboard</h1>
    <p class="mb-6 text-sm text-slate-500">{{ auth.user?.shop?.name }}</p>

    <div class="mb-6 flex gap-2 border-b border-slate-100">
      <button v-for="t in ['products', 'orders', 'batches']" :key="t" class="px-4 py-2 text-sm font-medium capitalize" :class="tab === t ? 'border-b-2 border-brand-600 text-brand-700' : 'text-slate-500'" @click="tab = t">{{ t }}</button>
    </div>

    <!-- Products -->
    <div v-show="tab === 'products'" class="grid gap-6 lg:grid-cols-[340px_1fr]">
      <form class="card h-fit space-y-3 p-4" @submit.prevent="save">
        <h2 class="font-semibold">{{ form.id ? 'Edit product' : 'New product' }}</h2>
        <input v-model="form.name" class="input" placeholder="Product name" required />
        <select v-model="form.category_id" class="input">
          <option value="">— category —</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <textarea v-model="form.description" class="input" rows="2" placeholder="Description" />
        <div class="flex gap-2">
          <label class="flex-1 text-xs text-slate-500">Price (₹)<input v-model="form.priceR" type="number" min="1" step="0.01" class="input" required /></label>
          <label class="flex-1 text-xs text-slate-500">Stock<input v-model="form.stock" type="number" min="0" class="input" /></label>
        </div>
        <div class="flex gap-4 text-sm">
          <label class="flex items-center gap-1"><input v-model="form.allow_full_buy" type="checkbox" /> 100% buy</label>
          <label class="flex items-center gap-1"><input v-model="form.allow_draw" type="checkbox" /> 1% draw</label>
        </div>
        <div class="flex gap-2">
          <button class="btn-primary flex-1" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
          <button v-if="form.id" type="button" class="btn-ghost" @click="reset">Cancel</button>
        </div>
      </form>

      <div class="space-y-2">
        <div v-for="p in products" :key="p.id" class="card flex items-center justify-between p-3">
          <div>
            <p class="font-semibold">{{ p.name }}</p>
            <p class="text-sm text-slate-500">{{ money(p.listed_price) }} · stock {{ p.stock }}
              <span v-if="p.allow_draw" class="chip ml-1 bg-accent-500/10 text-accent-600">draw</span>
            </p>
          </div>
          <div class="flex gap-2">
            <button class="btn-ghost px-3 py-1.5 text-sm" @click="edit(p)">Edit</button>
            <button class="px-2 text-rose-500 hover:text-rose-700" @click="remove(p)">✕</button>
          </div>
        </div>
        <p v-if="!products.length" class="card p-8 text-center text-slate-500">No products yet — add one.</p>
      </div>
    </div>

    <!-- Orders -->
    <div v-show="tab === 'orders'" class="space-y-2">
      <div v-for="o in orders" :key="o.id" class="card flex items-center justify-between p-3">
        <span>Order #{{ o.id }} · <span class="capitalize text-slate-500">{{ o.status }}</span></span>
        <span class="font-semibold">{{ money(o.payable) }}</span>
      </div>
      <p v-if="!orders.length" class="card p-8 text-center text-slate-500">No orders yet.</p>
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
      <p v-if="!batches.length" class="card p-8 text-center text-slate-500">No draw batches yet.</p>
    </div>
  </div>
</template>
