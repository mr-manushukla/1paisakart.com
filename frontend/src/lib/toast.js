import { reactive } from 'vue'

export const toasts = reactive([])
let seq = 0

export function toast(message, type = 'success') {
  const t = { id: ++seq, message, type }
  toasts.push(t)
  setTimeout(() => {
    const i = toasts.findIndex((x) => x.id === t.id)
    if (i > -1) toasts.splice(i, 1)
  }, 3500)
}

// Pull a human message out of an axios error (Laravel 422 { message } / validation bag).
export function apiError(err, fallback = 'Something went wrong.') {
  const d = err?.response?.data
  return d?.message || Object.values(d?.errors ?? {})[0]?.[0] || fallback
}
