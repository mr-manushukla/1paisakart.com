<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { toast, apiError } from '../lib/toast'

const auth = useAuthStore()
const router = useRouter()

const form = ref({ name: '', email: '', password: '', password_confirmation: '' })
const busy = ref(false)

async function submit() {
  busy.value = true
  try {
    await auth.register(form.value)
    toast(`Welcome to 1paisakart, ${auth.user.name.split(' ')[0]}!`)
    router.push({ name: 'home' })
  } catch (e) {
    toast(apiError(e, 'Registration failed'), 'error')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-md">
    <div class="card p-6">
      <h1 class="font-display text-2xl font-bold">Create your account</h1>
      <p class="mt-1 text-sm text-slate-500">Shop, join 1% draws, and track your wallet.</p>

      <form class="mt-5 space-y-3" @submit.prevent="submit">
        <input v-model="form.name" class="input" placeholder="Full name" required />
        <input v-model="form.email" type="email" class="input" placeholder="Email" required />
        <input v-model="form.password" type="password" class="input" placeholder="Password (min 8 chars)" required />
        <input v-model="form.password_confirmation" type="password" class="input" placeholder="Confirm password" required />
        <button class="btn-primary w-full" :disabled="busy">{{ busy ? 'Creating…' : 'Create account' }}</button>
      </form>

      <p class="mt-4 text-center text-sm text-slate-500">
        Already have an account? <RouterLink to="/login" class="font-semibold text-brand-700">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>
