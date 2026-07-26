<script setup>
import AddressBook from '../components/AddressBook.vue'
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="mb-1 font-display text-2xl font-bold">My addresses</h1>
    <p class="mb-6 text-sm text-slate-500">Where we deliver your orders and prizes. Your default address is picked automatically at checkout.</p>
    <AddressBook />
  </div>
</template>
