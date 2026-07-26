<script setup>
import { ref, onMounted } from 'vue'
import api from '../lib/api'
import { toast, apiError } from '../lib/toast'

/**
 * Lists the customer's delivery addresses and adds new ones. Shared by the
 * account page and checkout, so both stay in step. When `selectable` is set the
 * chosen address is exposed via v-model for checkout to send with the order.
 */
const props = defineProps({ selectable: Boolean })
const selected = defineModel({ type: Number, default: null })

const addresses = ref([])
const loading = ref(true)
const busy = ref(false)
const showForm = ref(false)
const blank = () => ({ label: 'Home', name: '', phone: '', line1: '', line2: '', city: '', state: '', pincode: '', is_default: false })
const form = ref(blank())

async function load() {
  try {
    addresses.value = (await api.get('/addresses')).data.data
    // Default to the customer's default address so checkout is one tap.
    if (props.selectable && !selected.value) {
      selected.value = (addresses.value.find((a) => a.is_default) ?? addresses.value[0])?.id ?? null
    }
    if (!addresses.value.length) showForm.value = true
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function save() {
  busy.value = true
  try {
    const { data } = await api.post('/addresses', form.value)
    toast('Address saved')
    form.value = blank()
    showForm.value = false
    await load()
    if (props.selectable) selected.value = data.data.id
  } catch (e) { toast(apiError(e), 'error') } finally { busy.value = false }
}

async function remove(a) {
  if (!confirm('Remove this address?')) return
  try {
    await api.delete(`/addresses/${a.id}`)
    if (selected.value === a.id) selected.value = null
    toast('Address removed')
    await load()
  } catch (e) { toast(apiError(e), 'error') }
}

async function makeDefault(a) {
  try {
    await api.put(`/addresses/${a.id}`, { ...a, is_default: true })
    await load()
  } catch (e) { toast(apiError(e), 'error') }
}

const oneLine = (a) => [a.line1, a.line2, a.city, `${a.state} ${a.pincode}`].filter(Boolean).join(', ')
</script>

<template>
  <div>
    <div v-if="loading" class="card h-28 animate-pulse bg-slate-50" />

    <div v-else class="space-y-3">
      <label
        v-for="a in addresses"
        :key="a.id"
        class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
        :class="selectable && selected === a.id ? 'border-brand-600 bg-brand-50/50' : 'border-slate-200 hover:bg-slate-50'"
      >
        <input v-if="selectable" v-model="selected" type="radio" :value="a.id" class="mt-1" />
        <span class="min-w-0 flex-1">
          <span class="flex flex-wrap items-center gap-2">
            <span class="font-semibold">{{ a.name }}</span>
            <span v-if="a.label" class="chip bg-slate-100 text-slate-600">{{ a.label }}</span>
            <span v-if="a.is_default" class="chip bg-brand-50 text-brand-700">Default</span>
          </span>
          <span class="block text-sm text-slate-600">{{ oneLine(a) }}</span>
          <span class="block text-xs text-slate-400">{{ a.phone }}</span>
        </span>
        <span class="flex flex-none flex-col items-end gap-1 text-xs">
          <button v-if="!a.is_default" type="button" class="text-slate-500 hover:text-brand-700" @click.prevent="makeDefault(a)">Set default</button>
          <button type="button" class="text-rose-500 hover:text-rose-700" @click.prevent="remove(a)">Remove</button>
        </span>
      </label>

      <p v-if="!addresses.length && !showForm" class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">
        No saved addresses yet.
      </p>

      <button v-if="!showForm" type="button" class="btn-ghost w-full text-sm" @click="showForm = true">+ Add a new address</button>

      <form v-else class="space-y-3 rounded-xl border border-slate-200 p-4" @submit.prevent="save">
        <p class="text-sm font-semibold">New delivery address</p>
        <div class="grid gap-3 sm:grid-cols-2">
          <input v-model="form.name" class="input" placeholder="Full name" required />
          <input v-model="form.phone" type="tel" class="input" placeholder="Mobile number" required />
        </div>
        <input v-model="form.line1" class="input" placeholder="House / flat, street" required />
        <input v-model="form.line2" class="input" placeholder="Area, landmark (optional)" />
        <div class="grid gap-3 sm:grid-cols-3">
          <input v-model="form.city" class="input" placeholder="City" required />
          <input v-model="form.state" class="input" placeholder="State" required />
          <input v-model="form.pincode" inputmode="numeric" maxlength="6" class="input" placeholder="PIN code" required />
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <input v-model="form.label" class="input" placeholder="Label (Home / Work)" />
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="form.is_default" type="checkbox" /> Make this my default address
          </label>
        </div>
        <div class="flex gap-2">
          <button class="btn-primary flex-1" :disabled="busy">{{ busy ? 'Saving…' : 'Save address' }}</button>
          <button v-if="addresses.length" type="button" class="btn-ghost" @click="showForm = false">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</template>
