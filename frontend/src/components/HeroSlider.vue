<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'
import { gradientClass, headingParts } from '../lib/gradients'

// Slides come from the admin dashboard (GET /api/slides falls back to config).
const slides = ref([])

const current = ref(0)
const autoplayOK = ref(false)
const manualStopped = ref(false)
let timer = null

const go = (i) => { current.value = (i + slides.value.length) % slides.value.length }
const next = () => go(current.value + 1)

function startAuto() { if (autoplayOK.value && !manualStopped.value && !timer && slides.value.length > 1) timer = setInterval(next, 6000) }
function pauseAuto() { if (timer) { clearInterval(timer); timer = null } }
function manualNav(fn) { manualStopped.value = true; pauseAuto(); fn() } // stop autoplay permanently on interaction

let startX = 0
const onTouchStart = (e) => { startX = e.changedTouches[0].clientX }
const onTouchEnd = (e) => {
  const dx = e.changedTouches[0].clientX - startX
  if (Math.abs(dx) > 40) manualNav(() => (dx < 0 ? next() : go(current.value - 1)))
}
function onKey(e) {
  if (e.key === 'ArrowRight') manualNav(next)
  else if (e.key === 'ArrowLeft') manualNav(() => go(current.value - 1))
}

onMounted(async () => {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const small = window.matchMedia('(max-width: 767px)').matches
  autoplayOK.value = !reduce && !small // no autoplay on mobile or reduced-motion

  try {
    slides.value = (await api.get('/slides')).data.data
  } catch { /* a dead banner shouldn't take the homepage down with it */ }

  startAuto()
})
onUnmounted(pauseAuto)
</script>

<template>
  <section
    v-if="slides.length"
    class="relative overflow-hidden rounded-3xl"
    role="region"
    aria-roledescription="carousel"
    aria-label="Featured promotions"
    tabindex="0"
    @mouseenter="pauseAuto"
    @mouseleave="startAuto"
    @touchstart.passive="onTouchStart"
    @touchend.passive="onTouchEnd"
    @keydown="onKey"
  >
    <transition name="fade" mode="out-in">
      <div
        :key="current"
        class="bg-gradient-to-br px-6 py-12 text-white sm:px-12 sm:py-16"
        :class="gradientClass(slides[current].gradient)"
        aria-roledescription="slide"
        :aria-label="`${current + 1} of ${slides.length}`"
        aria-live="polite"
      >
        <div class="flex items-center justify-between gap-8">
          <div class="max-w-xl">
            <span v-if="slides[current].eyebrow" class="chip bg-white/15 text-white">{{ slides[current].eyebrow }}</span>
            <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight sm:text-5xl">
              <template v-for="(part, i) in headingParts(slides[current].heading)" :key="i">
                <span v-if="i % 2" class="text-accent-400">{{ part }}</span>
                <template v-else>{{ part }}</template>
              </template>
            </h1>
            <p v-if="slides[current].text" class="mt-4 text-lg text-white/85">{{ slides[current].text }}</p>
            <RouterLink v-if="slides[current].cta_label && slides[current].cta_to" :to="slides[current].cta_to" class="btn-accent mt-6">
              {{ slides[current].cta_label }}
            </RouterLink>
          </div>
          <img v-if="slides[current].image" :src="slides[current].image" alt="" class="hidden h-56 w-56 flex-none rounded-2xl object-cover shadow-2xl ring-4 ring-white/10 lg:block" />
        </div>
      </div>
    </transition>

    <template v-if="slides.length > 1">
      <!-- Arrows -->
      <button class="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur hover:bg-white/30" aria-label="Previous slide" @click="manualNav(() => go(current - 1))">‹</button>
      <button class="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur hover:bg-white/30" aria-label="Next slide" @click="manualNav(next)">›</button>

      <!-- Dots -->
      <div class="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2">
        <button
          v-for="(s, i) in slides"
          :key="i"
          class="h-2.5 rounded-full transition-all"
          :class="i === current ? 'w-6 bg-white' : 'w-2.5 bg-white/40 hover:bg-white/60'"
          :aria-label="`Go to slide ${i + 1}`"
          :aria-current="i === current"
          @click="manualNav(() => go(i))"
        />
      </div>
    </template>
  </section>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.4s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
@media (prefers-reduced-motion: reduce) {
  .fade-enter-active, .fade-leave-active { transition: none; }
}
</style>
