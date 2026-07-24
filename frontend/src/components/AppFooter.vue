<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <footer class="mt-16 border-t border-slate-100 bg-white">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 md:grid-cols-4">
      <div>
        <div class="font-display text-lg font-extrabold"><span class="text-brand-600">1paisa</span><span class="text-accent-500">kart</span></div>
        <p class="mt-2 text-sm text-slate-500">Shop smart. Pay 1% for a shot at winning — or buy outright. Every draw pool is public.</p>
      </div>
      <div>
        <h4 class="font-semibold">Shop</h4>
        <ul class="mt-2 space-y-1 text-sm text-slate-500">
          <li><RouterLink to="/shop" class="hover:text-brand-700">All products</RouterLink></li>
          <li><RouterLink to="/shop?mode=draw" class="hover:text-brand-700">1% Draws</RouterLink></li>
          <li><RouterLink to="/winners" class="hover:text-brand-700">🏆 Winners</RouterLink></li>
        </ul>
      </div>
      <div>
        <h4 class="font-semibold">How it works</h4>
        <ul class="mt-2 space-y-1 text-sm text-slate-500">
          <li>Pay 1% to book a seat</li>
          <li>Pool fills → 1 winner keeps it</li>
          <li>Others: buy at the balance, or keep credit</li>
        </ul>
      </div>
      <div>
        <h4 class="font-semibold">Account</h4>
        <ul class="mt-2 space-y-1 text-sm text-slate-500">
          <li><RouterLink to="/login" class="hover:text-brand-700">Sign in</RouterLink></li>
          <li><RouterLink to="/register" class="hover:text-brand-700">Create account</RouterLink></li>
        </ul>
      </div>
    </div>
    <div class="border-t border-slate-100 py-4 text-center text-xs text-slate-400">© 2026 1paisakart · Demo build</div>
  </footer>
</template>
