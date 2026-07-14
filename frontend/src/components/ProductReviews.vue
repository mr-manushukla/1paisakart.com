<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'
import StarRating from './StarRating.vue'

const props = defineProps({ slug: { type: String, required: true } })
const auth = useAuthStore()

const reviews = ref([])
const summary = ref({ count: 0, average: null, can_review: false })
const loading = ref(true)
const form = ref({ rating: 5, body: '' })
const submitting = ref(false)

async function load() {
  const { data } = await api.get(`/products/${props.slug}/reviews`)
  reviews.value = data.data
  summary.value = data.summary
}
onMounted(async () => { try { await load() } finally { loading.value = false } })

async function submit() {
  submitting.value = true
  try {
    await api.post(`/products/${props.slug}/reviews`, form.value)
    toast('Thanks for your review!')
    await load()
  } catch (e) {
    toast(apiError(e), 'error')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="card p-6">
    <h2 class="font-display text-xl font-bold">Ratings &amp; reviews</h2>

    <div v-if="loading" class="mt-4 h-24 animate-pulse rounded-lg bg-slate-50" />
    <template v-else>
      <div class="mt-4 flex items-center gap-4">
        <div class="text-center">
          <p class="font-display text-4xl font-extrabold text-brand-700">{{ summary.average ?? '—' }}</p>
          <StarRating :value="summary.average || 0" />
          <p class="text-xs text-slate-500">{{ summary.count }} review{{ summary.count === 1 ? '' : 's' }}</p>
        </div>
      </div>

      <!-- Write a review -->
      <div class="mt-6 rounded-xl bg-slate-50 p-4">
        <template v-if="summary.can_review">
          <p class="mb-2 text-sm font-semibold">Write a review</p>
          <StarRating v-model:value="form.rating" interactive size="text-2xl" />
          <textarea v-model="form.body" class="input mt-2" rows="2" placeholder="Share your experience (optional)" />
          <button class="btn-primary mt-2" :disabled="submitting" @click="submit">{{ submitting ? 'Posting…' : 'Post review' }}</button>
        </template>
        <p v-else-if="!auth.isAuthed" class="text-sm text-slate-500">
          <RouterLink to="/login" class="font-semibold text-brand-700">Sign in</RouterLink> and buy this product to leave a review.
        </p>
        <p v-else class="text-sm text-slate-500">Only verified buyers of this product can review it.</p>
      </div>

      <!-- List -->
      <div v-if="reviews.length" class="mt-6 divide-y divide-slate-100">
        <div v-for="r in reviews" :key="r.id" class="py-4">
          <div class="flex items-center gap-2">
            <StarRating :value="r.rating" size="text-sm" />
            <span class="text-sm font-semibold">{{ r.author }}</span>
            <span v-if="r.is_mine" class="chip bg-brand-50 text-brand-700">You</span>
            <span class="chip bg-slate-100 text-slate-500">✓ Verified buyer</span>
          </div>
          <p v-if="r.body" class="mt-1 text-sm text-slate-600">{{ r.body }}</p>
        </div>
      </div>
      <p v-else class="mt-6 text-sm text-slate-500">No reviews yet — be the first!</p>
    </template>
  </section>
</template>
