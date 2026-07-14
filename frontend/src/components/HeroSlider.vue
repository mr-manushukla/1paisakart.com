<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'

// ≤3–5 slides; first = strongest offer (gets most exposure).
const slides = [
  {
    eyebrow: 'The 1% draw', a: 'Pay just ', hl: '1%', b: '. Win the whole thing.',
    text: 'Join a 100-seat pool for 1% of the price. When it fills, one random winner takes it — everyone else is refunded to wallet.',
    cta: { label: 'Explore 1% draws', to: '/shop?mode=draw' },
    grad: 'from-brand-600 to-brand-800',
    img: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&q=80&auto=format&fit=crop',
  },
  {
    eyebrow: 'Everyday value', a: 'Shop top products, ', hl: 'buy now', b: '.',
    text: 'Prefer certainty? Buy outright and put your wallet credit toward up to 10% off each item.',
    cta: { label: 'Shop all products', to: '/shop' },
    grad: 'from-slate-800 to-slate-950',
    img: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&q=80&auto=format&fit=crop',
  },
  {
    eyebrow: 'Transparent by design', a: 'Every pool is ', hl: '100% public', b: '.',
    text: 'See exactly who has joined each draw, live. No hidden odds — one winner in 100, refunds for the rest.',
    cta: { label: 'See a live pool', to: '/product/smart-watch-series-x' },
    grad: 'from-accent-500 to-accent-600',
    img: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&q=80&auto=format&fit=crop',
  },
]

const current = ref(0)
const autoplayOK = ref(false)
const manualStopped = ref(false)
let timer = null

const go = (i) => { current.value = (i + slides.length) % slides.length }
const next = () => go(current.value + 1)

function startAuto() { if (autoplayOK.value && !manualStopped.value && !timer) timer = setInterval(next, 6000) }
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

onMounted(() => {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const small = window.matchMedia('(max-width: 767px)').matches
  autoplayOK.value = !reduce && !small // no autoplay on mobile or reduced-motion
  startAuto()
})
onUnmounted(pauseAuto)
</script>

<template>
  <section
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
        :class="slides[current].grad"
        aria-roledescription="slide"
        :aria-label="`${current + 1} of ${slides.length}`"
        aria-live="polite"
      >
        <div class="flex items-center justify-between gap-8">
          <div class="max-w-xl">
            <span class="chip bg-white/15 text-white">{{ slides[current].eyebrow }}</span>
            <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight sm:text-5xl">
              {{ slides[current].a }}<span class="text-accent-400">{{ slides[current].hl }}</span>{{ slides[current].b }}
            </h1>
            <p class="mt-4 text-lg text-white/85">{{ slides[current].text }}</p>
            <RouterLink :to="slides[current].cta.to" class="btn-accent mt-6">{{ slides[current].cta.label }}</RouterLink>
          </div>
          <img :src="slides[current].img" alt="" class="hidden h-56 w-56 flex-none rounded-2xl object-cover shadow-2xl ring-4 ring-white/10 lg:block" />
        </div>
      </div>
    </transition>

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
  </section>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.4s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
@media (prefers-reduced-motion: reduce) {
  .fade-enter-active, .fade-leave-active { transition: none; }
}
</style>
