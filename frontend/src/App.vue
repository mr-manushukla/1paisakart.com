<script setup>
import { RouterView } from 'vue-router'
import AppHeader from './components/AppHeader.vue'
import AppFooter from './components/AppFooter.vue'
import ToastHost from './components/ToastHost.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <AppHeader />
    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
      <RouterView />
    </main>
    <AppFooter />
    <ToastHost />
  </div>
</template>
