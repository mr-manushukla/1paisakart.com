<script setup>
import { ref, computed, watch } from 'vue'
import ProductImage from './ProductImage.vue'

const props = defineProps({
  images: { type: Array, default: () => [] },
  name: { type: String, default: '' },
})

const active = ref(0)
const zoom = ref(false)
watch(() => props.images, () => { active.value = 0 })

const list = computed(() => (props.images.length ? props.images : [null]))
// Request a larger variant for the zoom view when it's an Unsplash URL.
const zoomSrc = computed(() => (list.value[active.value] || '').replace(/w=\d+/, 'w=1400'))
</script>

<template>
  <div class="flex gap-3">
    <!-- Desktop: vertical thumbnails -->
    <div v-if="list.length > 1" class="hidden md:flex md:w-16 md:flex-col md:gap-2">
      <button
        v-for="(img, i) in list"
        :key="i"
        class="aspect-square overflow-hidden rounded-lg border-2 transition"
        :class="i === active ? 'border-brand-600' : 'border-transparent hover:border-slate-300'"
        :aria-label="`View image ${i + 1} of ${list.length}`"
        :aria-current="i === active"
        @click="active = i"
      >
        <img :src="img" :alt="`${name} thumbnail ${i + 1}`" loading="lazy" class="h-full w-full object-cover" />
      </button>
    </div>

    <div class="min-w-0 flex-1">
      <div class="relative aspect-square cursor-zoom-in overflow-hidden rounded-xl bg-slate-100" @click="zoom = true">
        <ProductImage v-if="!list[active]" :name="name" />
        <img v-else :src="list[active]" :alt="name" :loading="active === 0 ? 'eager' : 'lazy'" class="h-full w-full object-cover" />
        <span v-if="list[active]" class="chip absolute bottom-2 right-2 bg-black/50 text-white">🔍 Tap to zoom</span>
      </div>

      <!-- Mobile: horizontal thumbnail strip -->
      <div v-if="list.length > 1" class="mt-2 flex gap-2 overflow-x-auto md:hidden">
        <button
          v-for="(img, i) in list"
          :key="i"
          class="h-14 w-14 flex-none overflow-hidden rounded-lg border-2"
          :class="i === active ? 'border-brand-600' : 'border-transparent'"
          :aria-label="`View image ${i + 1}`"
          @click="active = i"
        >
          <img :src="img" :alt="`thumbnail ${i + 1}`" loading="lazy" class="h-full w-full object-cover" />
        </button>
      </div>
    </div>
  </div>

  <!-- Zoom overlay (genuine hi-res variant) -->
  <div v-if="zoom" class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4" @click="zoom = false">
    <img :src="zoomSrc" :alt="name" class="max-h-full max-w-full rounded-lg" />
    <button class="absolute right-4 top-4 text-3xl text-white/80 hover:text-white" aria-label="Close">×</button>
  </div>
</template>
