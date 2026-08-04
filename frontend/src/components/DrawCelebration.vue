<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'

/**
 * When a pool draws while people are on the site, everyone gets a short
 * celebration at the top of the screen.
 *
 * There's no websocket here, so it polls the public winner board and fires when
 * a pool it hasn't seen before appears. The last-seen id is remembered per
 * browser, so a returning visitor isn't greeted by a draw from last week.
 */
const SEEN_KEY = 'paisa_last_draw_seen'
const POLL_MS = 30000

const show = ref(false)
const pool = ref(null)
let timer = null
let reduceMotion = false

// A fixed set of drift/delay pairs keeps the confetti varied without random(),
// so the burst looks the same on every device.
const pieces = Array.from({ length: 26 }, (_, i) => ({
  left: (i * 3.9) % 100,
  delay: ((i * 137) % 900) / 1000,
  drift: ((i % 5) - 2) * 18,
  hue: ['#059669', '#f59e0b', '#ef4444', '#3b82f6', '#a855f7'][i % 5],
  size: 6 + (i % 3) * 3,
}))

const productName = computed(() => pool.value?.winner?.product ?? 'a product')

async function check() {
  try {
    const { data } = await api.get('/winners')
    const latest = data.data?.[0]
    if (!latest) return

    const seen = localStorage.getItem(SEEN_KEY)
    // First visit just records where we are — no celebration for old draws.
    if (seen === null) return localStorage.setItem(SEEN_KEY, String(latest.id))
    if (String(latest.id) === seen) return

    localStorage.setItem(SEEN_KEY, String(latest.id))
    pool.value = latest
    show.value = true
    // Long enough that someone glancing away still catches it; dismissible too.
    setTimeout(() => { show.value = false }, 14000)
  } catch { /* a missed poll is not worth surfacing */ }
}

onMounted(() => {
  reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  check()
  timer = setInterval(check, POLL_MS)
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="pointer-events-none fixed inset-x-0 top-0 z-[60]">
      <!-- confetti -->
      <div v-if="!reduceMotion" class="absolute inset-x-0 top-0 h-64 overflow-hidden">
        <span
          v-for="(p, i) in pieces"
          :key="i"
          class="confetti absolute top-0 block rounded-[2px]"
          :style="{
            left: p.left + '%',
            width: p.size + 'px',
            height: p.size * 1.6 + 'px',
            background: p.hue,
            animationDelay: p.delay + 's',
            '--drift': p.drift + 'px',
          }"
        />
      </div>

      <!-- banner -->
      <div class="mx-auto mt-3 w-fit max-w-[92vw] px-3">
        <div class="pointer-events-auto flex items-center gap-3 rounded-2xl bg-gradient-to-r from-brand-700 to-brand-900 px-4 py-2.5 text-white shadow-xl">
          <span class="text-xl">🎉</span>
          <p class="min-w-0 text-sm">
            <span class="font-semibold">A pool just drew!</span>
            <span class="ml-1 text-white/85">{{ pool?.winner?.name }} won {{ productName }}.</span>
          </p>
          <RouterLink to="/winners" class="shrink-0 rounded-lg bg-white/15 px-2.5 py-1 text-xs font-semibold hover:bg-white/25">
            See winners
          </RouterLink>
          <button class="shrink-0 text-lg leading-none text-white/70 hover:text-white" aria-label="Dismiss" @click="show = false">×</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.confetti {
  animation: confetti-fall 2.8s ease-in forwards;
  opacity: 0;
}
@keyframes confetti-fall {
  0%   { transform: translateY(-20px) translateX(0) rotate(0deg); opacity: 1; }
  100% { transform: translateY(260px) translateX(var(--drift)) rotate(540deg); opacity: 0; }
}
</style>
