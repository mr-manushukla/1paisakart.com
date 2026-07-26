<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../lib/api'
import { money } from '../lib/money'
import ProductImage from '../components/ProductImage.vue'

const pools = ref([])
const loading = ref(true)
const open = ref(null) // which participant seat is expanded (per pool: `${poolId}:${seat}`)

onMounted(async () => {
  try {
    pools.value = (await api.get('/winners')).data.data
  } finally {
    loading.value = false
  }
})

const drawnOn = (iso) => new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
const toggle = (k) => { open.value = open.value === k ? null : k }
</script>

<template>
  <div>
    <!-- Hero -->
    <div class="mb-8 rounded-3xl bg-gradient-to-br from-brand-600 to-brand-800 px-6 py-10 text-center text-white sm:py-14">
      <p class="text-sm font-semibold uppercase tracking-wide text-accent-300">🏆 Winner board</p>
      <h1 class="mt-2 font-display text-3xl font-extrabold sm:text-4xl">Every draw, every winner — in the open</h1>
      <p class="mx-auto mt-3 max-w-2xl text-white/85">
        When a pool fills, one seat wins the product for their 1%. Here's every pool that's drawn, with the winner and everyone who played — fully public.
      </p>
    </div>

    <div v-if="loading" class="space-y-4">
      <div v-for="n in 3" :key="n" class="card h-56 animate-pulse bg-slate-50" />
    </div>

    <div v-else-if="!pools.length" class="card p-12 text-center text-slate-500">
      No draws have completed yet. <RouterLink to="/shop?mode=draw" class="font-semibold text-brand-700">Join a live pool →</RouterLink>
    </div>

    <div v-else class="space-y-6">
      <article v-for="p in pools" :key="p.id" class="card overflow-hidden p-0">
        <!-- Pool header -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="chip bg-brand-50 text-brand-700">{{ p.club }}</span>
            <span class="text-sm font-semibold text-slate-700">Draw #{{ p.batch_no }}</span>
            <span class="text-xs text-slate-400">· Pool #{{ p.id }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
            <!-- The pool's collected total is admin-only; the API doesn't send it here. -->
            <span>Drawn {{ drawnOn(p.drawn_at) }}</span>
            <span>·</span>
            <span>{{ p.size }} seats</span>
          </div>
        </div>

        <!-- Winner spotlight -->
        <div v-if="p.winner" class="flex items-center gap-4 bg-gradient-to-r from-accent-500/10 to-transparent px-5 py-4">
          <div class="relative h-20 w-20 flex-none">
            <ProductImage :src="p.winner.product_image" :name="p.winner.product" />
            <span class="absolute -left-2 -top-2 text-2xl">🏆</span>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-accent-600">Winner · Seat {{ p.winner.seat }}</p>
            <p class="truncate font-display text-lg font-bold text-slate-800">{{ p.winner.name }}</p>
            <p class="truncate text-sm text-slate-600">
              won <span class="font-semibold">{{ p.winner.product }}</span>
              <span class="text-slate-400"> · worth {{ money(p.winner.product_value) }}</span>
            </p>
          </div>
        </div>

        <!-- Participants: winner highlighted among the rest -->
        <div class="px-5 pb-5 pt-4">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">All {{ p.participants.length }} participants</p>
          <div class="grid grid-cols-6 gap-1.5 sm:grid-cols-10">
            <button
              v-for="s in p.participants"
              :key="s.seat"
              type="button"
              class="flex aspect-square flex-col items-center justify-center rounded-lg text-[10px] font-semibold transition"
              :class="s.is_winner
                ? 'bg-accent-500 text-white ring-2 ring-accent-500/40'
                : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
              :title="`Seat ${s.seat} · ${s.name} · ${s.product}`"
              @click="toggle(`${p.id}:${s.seat}`)"
            >
              <span v-if="s.is_winner" class="text-sm leading-none">👑</span>
              <span v-else>{{ s.seat }}</span>
            </button>
          </div>

          <!-- Tapped seat detail (works on touch, where title never fires) -->
          <p
            v-for="s in p.participants.filter((x) => open === `${p.id}:${x.seat}`)"
            :key="'d' + s.seat"
            class="mt-3 rounded-lg px-3 py-2 text-sm"
            :class="s.is_winner ? 'bg-accent-500/10 text-accent-700' : 'bg-slate-50 text-slate-600'"
          >
            <span class="font-semibold">Seat {{ s.seat }}{{ s.is_winner ? ' · 🏆 Winner' : '' }}</span>
            — {{ s.name }} booked <span class="font-medium">{{ s.product }}</span>
          </p>
        </div>
      </article>
    </div>
  </div>
</template>
