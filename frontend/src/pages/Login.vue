<script setup>
import { ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const busy = ref(false)

const demos = [
  { label: 'Customer', email: 'customer43@1paisakart.test' },
  { label: 'Vendor', email: 'vendor1@1paisakart.test' },
  { label: 'Admin', email: 'admin@1paisakart.test' },
]
function fill(d) { email.value = d.email; password.value = 'password' }

async function submit() {
  busy.value = true
  try {
    await auth.login(email.value, password.value)
    toast(`Welcome back, ${auth.user.name.split(' ')[0]}!`)
    router.push(route.query.redirect || { name: auth.homeRouteName })
  } catch (e) {
    toast(apiError(e, 'Login failed'), 'error')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-md">
    <div class="card p-6">
      <h1 class="font-display text-2xl font-bold">Sign in</h1>
      <p class="mt-1 text-sm text-slate-500">Welcome back to 1paisakart.</p>

      <form class="mt-5 space-y-3" @submit.prevent="submit">
        <input v-model="email" type="email" class="input" placeholder="Email" required />
        <input v-model="password" type="password" class="input" placeholder="Password" required />
        <button class="btn-primary w-full" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</button>
      </form>

      <div class="mt-4 rounded-lg bg-slate-50 p-3">
        <p class="text-xs font-semibold text-slate-500">Demo accounts (password: <code>password</code>)</p>
        <div class="mt-2 flex gap-2">
          <button v-for="d in demos" :key="d.label" class="btn-ghost flex-1 px-2 py-1 text-xs" @click="fill(d)">{{ d.label }}</button>
        </div>
      </div>

      <p class="mt-4 text-center text-sm text-slate-500">
        New here? <RouterLink to="/register" class="font-semibold text-brand-700">Create an account</RouterLink>
      </p>
    </div>
  </div>
</template>
