<script setup>
import { RouterLink } from 'vue-router'
import { useWishlistStore } from '../stores/wishlist'
import ProductCard from '../components/ProductCard.vue'

const wl = useWishlistStore()
</script>

<template>
  <div>
    <h1 class="mb-6 font-display text-2xl font-bold">Saved items <span class="text-slate-400">({{ wl.count }})</span></h1>

    <div v-if="!wl.count" class="card p-10 text-center">
      <p class="text-slate-500">Nothing saved yet. Tap the ♥ on any product to save it for later.</p>
      <RouterLink to="/shop" class="btn-primary mt-4 inline-flex">Browse products</RouterLink>
    </div>
    <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-4">
      <ProductCard v-for="p in wl.items" :key="p.id" :product="p" />
    </div>
  </div>
</template>
