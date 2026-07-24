<script setup>
import { ref } from 'vue'

// A normal password input with an eye toggle. v-model works as usual.
defineProps({ placeholder: { type: String, default: 'Password' } })
const model = defineModel({ type: String, default: '' })
const show = ref(false)
</script>

<template>
  <div class="relative">
    <input
      :type="show ? 'text' : 'password'"
      :value="model"
      class="input pr-11"
      :placeholder="placeholder"
      autocomplete="current-password"
      required
      @input="model = $event.target.value"
    />
    <button
      type="button"
      class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600"
      :aria-label="show ? 'Hide password' : 'Show password'"
      :aria-pressed="show"
      @click="show = !show"
    >
      <!-- eye -->
      <svg v-if="!show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1 1 0 0 1 0-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178a1 1 0 0 1 0 .644C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
        <circle cx="12" cy="12" r="3" />
      </svg>
      <!-- eye-off -->
      <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 2.036 11.68a1 1 0 0 0 0 .644C3.423 16.49 7.36 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.638 0 8.573 3.007 9.963 7.178a1 1 0 0 1 0 .644 10.51 10.51 0 0 1-1.943 3.457M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.243 4.243L9.88 9.88" />
      </svg>
    </button>
  </div>
</template>
