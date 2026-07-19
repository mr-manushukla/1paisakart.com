<script setup>
import { ref, computed } from 'vue'
import api from '../lib/api'
import { toast, apiError } from '../lib/toast'

const props = defineProps({ product: { type: Object, required: true } })
const emit = defineEmits(['updated'])

const MAX = 5
const busy = ref(false)
const images = computed(() => props.product.images ?? [])
const full = computed(() => images.value.length >= MAX)

async function upload(e) {
  const files = [...e.target.files]
  e.target.value = '' // allow re-selecting the same file
  if (!files.length) return

  const fd = new FormData()
  files.forEach((f) => fd.append('images[]', f))
  busy.value = true
  try {
    const { data } = await api.post(`/vendor/products/${props.product.id}/images`, fd)
    emit('updated', data.data)
    toast(`${files.length} image${files.length > 1 ? 's' : ''} uploaded`)
  } catch (err) {
    toast(apiError(err), 'error')
  } finally {
    busy.value = false
  }
}

async function makePrimary(url) {
  busy.value = true
  try {
    const { data } = await api.post(`/vendor/products/${props.product.id}/images/primary`, { url })
    emit('updated', data.data)
    toast('Primary image updated')
  } catch (err) { toast(apiError(err), 'error') } finally { busy.value = false }
}

async function remove(url) {
  if (!confirm('Remove this image?')) return
  busy.value = true
  try {
    const { data } = await api.delete(`/vendor/products/${props.product.id}/images`, { data: { url } })
    emit('updated', data.data)
    toast('Image removed')
  } catch (err) { toast(apiError(err), 'error') } finally { busy.value = false }
}
</script>

<template>
  <div>
    <div class="mb-2 flex items-center justify-between">
      <p class="text-sm font-semibold">Product images</p>
      <span class="text-xs" :class="full ? 'text-amber-600' : 'text-slate-400'">{{ images.length }} / {{ MAX }}</span>
    </div>

    <div v-if="images.length" class="grid grid-cols-3 gap-2 sm:grid-cols-5">
      <div v-for="(url, i) in images" :key="url" class="group relative aspect-square overflow-hidden rounded-lg border border-slate-200">
        <img :src="url" alt="" class="h-full w-full object-cover" />
        <span v-if="i === 0" class="chip absolute left-1 top-1 bg-brand-600 px-1.5 py-0.5 text-[10px] text-white">Primary</span>
        <div class="absolute inset-0 flex items-center justify-center gap-1 bg-black/50 opacity-0 transition group-hover:opacity-100">
          <button v-if="i !== 0" type="button" class="rounded bg-white/90 px-1.5 py-0.5 text-[11px] font-medium" :disabled="busy" @click="makePrimary(url)">Primary</button>
          <button type="button" class="rounded bg-rose-600 px-1.5 py-0.5 text-[11px] font-medium text-white" :disabled="busy" @click="remove(url)">Remove</button>
        </div>
      </div>
    </div>
    <p v-else class="rounded-lg border border-dashed border-slate-200 p-4 text-center text-sm text-slate-400">No images yet</p>

    <label class="mt-3 block">
      <span class="sr-only">Upload images</span>
      <input
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        :disabled="busy || full"
        class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-700 disabled:opacity-50"
        @change="upload"
      />
    </label>
    <p class="mt-1 text-xs text-slate-400">
      {{ full ? 'Limit reached — remove one to add another.' : 'JPG, PNG or WebP · up to 4 MB each · first image is the thumbnail.' }}
    </p>
  </div>
</template>
