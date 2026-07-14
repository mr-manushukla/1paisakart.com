<script setup>
import { computed } from 'vue'
import { toast } from '../lib/toast'

const props = defineProps({ title: { type: String, default: '' } })

const url = computed(() => (typeof window !== 'undefined' ? window.location.href : ''))
const canNative = typeof navigator !== 'undefined' && !!navigator.share
const enc = encodeURIComponent

const links = computed(() => ({
  WhatsApp: `https://wa.me/?text=${enc(props.title + ' ' + url.value)}`,
  X: `https://twitter.com/intent/tweet?text=${enc(props.title)}&url=${enc(url.value)}`,
  Facebook: `https://www.facebook.com/sharer/sharer.php?u=${enc(url.value)}`,
  Telegram: `https://t.me/share/url?url=${enc(url.value)}&text=${enc(props.title)}`,
}))

async function nativeShare() {
  try { await navigator.share({ title: props.title, url: url.value }) } catch {}
}
async function copy() {
  try { await navigator.clipboard.writeText(url.value); toast('Link copied') } catch { toast('Could not copy link', 'error') }
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <span class="text-sm text-slate-500">Share:</span>
    <button v-if="canNative" class="btn-ghost px-3 py-1.5 text-xs" @click="nativeShare">🔗 Share</button>
    <a
      v-for="(href, name) in links"
      :key="name"
      :href="href"
      target="_blank"
      rel="noopener noreferrer"
      class="btn-ghost px-3 py-1.5 text-xs"
    >{{ name }}</a>
    <button class="btn-ghost px-3 py-1.5 text-xs" @click="copy">Copy link</button>
  </div>
</template>
