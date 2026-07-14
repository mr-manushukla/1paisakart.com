<script setup>
import { toasts } from '../lib/toast'
</script>

<template>
  <div class="fixed right-4 top-4 z-50 flex flex-col gap-2">
    <transition-group name="toast">
      <div
        v-for="t in toasts"
        :key="t.id"
        class="rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
        :class="t.type === 'error' ? 'bg-rose-600' : 'bg-brand-600'"
      >
        {{ t.message }}
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(20px); }
.toast-enter-active, .toast-leave-active { transition: all 0.25s ease; }
</style>
