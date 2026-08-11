<script setup>
import { ref, computed } from 'vue'

/**
 * Period picker shared by My Draws and My Orders → Winnings, so both filter the
 * same way. Exposes { from, to } as yyyy-mm-dd (null = unbounded) — the shape
 * /my-draws takes.
 */
const range = defineModel({ type: Object, default: () => ({ from: null, to: null }) })

const mode = ref('all') // all | month | year | custom
const now = new Date()

/**
 * Local yyyy-mm-dd. toISOString() would convert to UTC first, and in IST that
 * pulls the 1st of a month back into the previous one.
 */
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

const month = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`)
const year = ref(now.getFullYear())
const from = ref('')
const to = ref('')

const years = computed(() => Array.from({ length: 6 }, (_, i) => now.getFullYear() - i))

function apply() {
  if (mode.value === 'all') return (range.value = { from: null, to: null })

  if (mode.value === 'month') {
    const [y, m] = month.value.split('-').map(Number)
    // Day 0 of the next month is the last day of this one — no month-length table.
    return (range.value = { from: iso(new Date(y, m - 1, 1)), to: iso(new Date(y, m, 0)) })
  }
  if (mode.value === 'year') {
    return (range.value = { from: `${year.value}-01-01`, to: `${year.value}-12-31` })
  }
  range.value = { from: from.value || null, to: to.value || null }
}

function pick(m) {
  mode.value = m
  apply()
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <button
      v-for="m in [['all', 'All time'], ['month', 'Monthly'], ['year', 'Yearly'], ['custom', 'Custom range']]"
      :key="m[0]"
      type="button"
      class="chip border text-xs"
      :class="mode === m[0] ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 bg-white text-slate-500'"
      @click="pick(m[0])"
    >
      {{ m[1] }}
    </button>

    <input
      v-if="mode === 'month'"
      v-model="month"
      type="month"
      class="input w-auto py-1 text-sm"
      @change="apply"
    />

    <select v-if="mode === 'year'" v-model.number="year" class="input w-auto py-1 text-sm" @change="apply">
      <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
    </select>

    <template v-if="mode === 'custom'">
      <input v-model="from" type="date" :max="to || undefined" class="input w-auto py-1 text-sm" @change="apply" />
      <span class="text-xs text-slate-400">to</span>
      <input v-model="to" type="date" :min="from || undefined" class="input w-auto py-1 text-sm" @change="apply" />
    </template>
  </div>
</template>
