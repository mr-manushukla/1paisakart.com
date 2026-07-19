<script setup>
import { ref, onMounted } from 'vue'
import api from '../lib/api'
import { money } from '../lib/money'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'
import ProductImageManager from '../components/ProductImageManager.vue'

const auth = useAuthStore()
const tab = ref('products')

const products = ref([])
const orders = ref([])
const batches = ref([])
const categories = ref([])

// Common product-information fields (rendered as a spec table on the product page).
const SPEC_PRESETS = ['Model', 'Colour', 'Size', 'Material', 'Weight', 'Dimensions', 'Care', 'Shipping']

const blank = () => ({
  id: null, name: '', brand: '', category_id: '', description: '',
  priceR: '', stock: 0, allow_full_buy: true, status: 'active', specs: [],
})
const form = ref(blank())
const current = ref(null)   // full product being edited (for the image manager)
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
    id: p.id,
    name: p.name,
    brand: p.brand || '',
    category_id: p.category?.slug ? categories.value.find((c) => c.slug === p.category.slug)?.id : '',
    description: p.description || '',
    priceR: p.listed_price / 100,
    stock: p.stock,
    allow_full_buy: p.allow_full_buy,
    status: 'active',
    specs: (p.specs || []).map((s) => ({ ...s })),
  }
  current.value = p
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
function reset() { form.value = blank(); current.value = null }

function addSpec(label = '') { form.value.specs.push({ label, value: '' }) }
function removeSpec(i) { form.value.specs.splice(i, 1) }

async function save() {
  saving.value = true
  const payload = {
    name: form.value.name,
    brand: form.value.brand || null,
    category_id: form.value.category_id || null,
    description: form.value.description,
    listed_price: Math.round(Number(form.value.priceR) * 100),
    stock: Number(form.value.stock),
    allow_full_buy: form.value.allow_full_buy,
    status: form.value.status,
    specs: form.value.specs.filter((s) => s.label && s.value),
  }
  try {
    const { data } = form.value.id
      ? await api.put(`/vendor/products/${form.value.id}`, payload)
      : await api.post('/vendor/products', payload)
    // Stay in edit mode so images can be added straight away.
    form.value.id = data.data.id
    current.value = data.data
    toast('Product saved — you can add images below')
    await Promise.all([loadProducts(), loadBatches()])
  } catch (e) { toast(apiError(e), 'error') } finally { saving.value = false }
}

function onImagesUpdated(product) {
  current.value = product
  loadProducts()
}

async function remove(p) {
  if (!confirm(`Delete "${p.name}"?`)) return
  try {
    await api.delete(`/vendor/products/${p.id}`)
    toast('Deleted')
    if (form.value.id === p.id) reset()
    await loadProducts()
  } catch (e) { toast(apiError(e), 'error') }
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
    <div v-show="tab === 'products'" class="grid gap-6 lg:grid-cols-[400px_1fr]">
      <div class="space-y-4">
        <form class="card space-y-4 p-4" @submit.prevent="save">
          <div class="flex items-center justify-between">
            <h2 class="font-semibold">{{ form.id ? 'Edit product' : 'New product' }}</h2>
            <button v-if="form.id" type="button" class="text-xs text-slate-500 hover:text-brand-700" @click="reset">+ New</button>
          </div>

          <!-- Basics -->
          <div class="space-y-3">
            <input v-model="form.name" class="input" placeholder="Product name *" required />
            <div class="flex gap-2">
              <input v-model="form.brand" class="input flex-1" placeholder="Brand" />
              <select v-model="form.category_id" class="input flex-1">
                <option value="">— category —</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <textarea v-model="form.description" class="input" rows="3" placeholder="Description" />
            <div class="flex gap-2">
              <label class="flex-1 text-xs text-slate-500">Price (₹) *<input v-model="form.priceR" type="number" min="1" step="0.01" class="input" required /></label>
              <label class="flex-1 text-xs text-slate-500">Stock<input v-model="form.stock" type="number" min="0" class="input" /></label>
            </div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.allow_full_buy" type="checkbox" /> Available to buy outright (100%)</label>
            <p class="rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-800">
              🎲 The <strong>1% lucky draw applies automatically</strong> to every product priced between ₹100 and ₹5,00,000 — no setup needed.
            </p>
          </div>

          <!-- Product information -->
          <div class="border-t border-slate-100 pt-3">
            <div class="mb-2 flex items-center justify-between">
              <p class="text-sm font-semibold">Product information</p>
              <button type="button" class="text-xs text-brand-700 hover:underline" @click="addSpec()">+ Add field</button>
            </div>
            <div v-for="(s, i) in form.specs" :key="i" class="mb-2 flex gap-2">
              <input v-model="s.label" class="input w-32 flex-none text-sm" placeholder="Label" />
              <input v-model="s.value" class="input flex-1 text-sm" placeholder="Value" />
              <button type="button" class="px-1 text-rose-500 hover:text-rose-700" @click="removeSpec(i)">✕</button>
            </div>
            <div class="flex flex-wrap gap-1">
              <button
                v-for="p in SPEC_PRESETS"
                :key="p"
                type="button"
                class="chip border border-slate-200 bg-white text-slate-600 hover:border-brand-500 hover:text-brand-700"
                :disabled="form.specs.some((s) => s.label === p)"
                @click="addSpec(p)"
              >+ {{ p }}</button>
            </div>
          </div>

          <button class="btn-primary w-full" :disabled="saving">{{ saving ? 'Saving…' : form.id ? 'Save changes' : 'Create product' }}</button>
        </form>

        <!-- Images (needs an existing product to attach to) -->
        <div class="card p-4">
          <ProductImageManager v-if="current?.id" :product="current" @updated="onImagesUpdated" />
          <template v-else>
            <p class="text-sm font-semibold">Product images</p>
            <p class="mt-1 text-xs text-slate-400">Save the product first, then upload up to 5 images here.</p>
          </template>
        </div>
      </div>

      <!-- List -->
      <div class="space-y-2">
        <div v-for="p in products" :key="p.id" class="card flex items-center gap-3 p-3">
          <img v-if="p.image" :src="p.image" alt="" class="h-14 w-14 flex-none rounded-lg object-cover" />
          <div v-else class="flex h-14 w-14 flex-none items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-400">no img</div>
          <div class="min-w-0 flex-1">
            <p class="truncate font-semibold">{{ p.name }} <span v-if="p.brand" class="font-normal text-slate-400">· {{ p.brand }}</span></p>
            <p class="text-sm text-slate-500">
              {{ money(p.listed_price) }} · stock {{ p.stock }} · {{ (p.images || []).length }} img
              <span v-if="p.draw_eligible" class="chip ml-1 bg-accent-500/10 text-accent-600">1% draw</span>
            </p>
          </div>
          <div class="flex flex-none gap-2">
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
      <p class="text-sm text-slate-500">Club pools that contain bookings for your products. Pools are shared across vendors, so only an admin can cancel one.</p>
      <div v-for="b in batches" :key="b.id" class="card flex items-center justify-between p-3">
        <div>
          <p class="font-semibold">{{ b.club }} · pool #{{ b.batch_no }}</p>
          <p class="text-sm text-slate-500">{{ b.filled }}/{{ b.size }} seats · {{ b.my_bookings }} of yours · <span class="capitalize">{{ b.status }}</span></p>
        </div>
      </div>
      <p v-if="!batches.length" class="card p-8 text-center text-slate-500">No club pools with your products yet.</p>
    </div>
  </div>
</template>
