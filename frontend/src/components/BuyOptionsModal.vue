<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { buyOptionsProduct, buyOptionsQty, closeBuyOptions } from '../lib/buyOptions'
import { useCartStore } from '../stores/cart'
import { money } from '../lib/money'
import { toast } from '../lib/toast'
import ProductImage from './ProductImage.vue'

const cart = useCartStore()
const router = useRouter()
const panel = ref(null)

const product = computed(() => buyOptionsProduct.value)
const canBuy = computed(() => !!product.value?.allow_full_buy && product.value?.stock > 0)
const canDraw = computed(() => !!product.value?.draw_eligible)
const alreadyBooked = computed(() => product.value && cart.has('draw', product.value.id))

const qty = computed(() => buyOptionsQty.value)

function addBuy() {
  cart.add(product.value, qty.value, 'buy')
  toast(`${qty.value > 1 ? qty.value + ' × ' : ''}${product.value.name} added to cart`)
  closeBuyOptions()
}
function addDraw() {
  if (alreadyBooked.value) {
    toast('That 1% booking is already in your cart')
    return closeBuyOptions()
  }
  cart.add(product.value, 1, 'draw')
  toast('1% booking added to cart')
  closeBuyOptions()
}
function goToCart() { closeBuyOptions(); router.push({ name: 'cart' }) }

const onKey = (e) => { if (e.key === 'Escape') closeBuyOptions() }
onMounted(() => document.addEventListener('keydown', onKey))
onUnmounted(() => document.removeEventListener('keydown', onKey))
watch(product, async (p) => { if (p) { await new Promise((r) => setTimeout(r)); panel.value?.focus() } })
</script>

<template>
  <div
    v-if="product"
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
    @click.self="closeBuyOptions"
  >
    <div
      ref="panel"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
      aria-label="Choose how to buy"
      class="w-full max-w-md rounded-t-2xl bg-white p-5 shadow-xl outline-none sm:rounded-2xl"
    >
      <div class="flex items-start gap-3">
        <div class="h-16 w-16 flex-none"><ProductImage :src="product.image" :name="product.name" /></div>
        <div class="min-w-0 flex-1">
          <p class="truncate font-semibold">{{ product.name }}</p>
          <p class="text-sm">
            <span class="font-bold text-brand-700">{{ money(product.listed_price) }}</span>
            <template v-if="product.mrp">
              <span class="ml-1 text-xs text-slate-400 line-through">{{ money(product.mrp) }}</span>
              <span class="ml-1 text-xs font-bold text-green-700">↓{{ product.discount_pct }}%</span>
            </template>
          </p>
        </div>
        <button class="text-2xl leading-none text-slate-400 hover:text-slate-600" aria-label="Close" @click="closeBuyOptions">×</button>
      </div>

      <p class="mt-4 text-sm font-semibold text-slate-700">How would you like to buy this?</p>

      <!-- 1% first: it's the headline offer, so it gets the top slot. -->
      <div class="mt-3 space-y-2">
        <!-- 1% advance -->
        <button
          v-if="canDraw"
          class="flex w-full items-center justify-between rounded-xl border-2 border-accent-500/50 bg-accent-500/5 px-4 py-3 text-left transition hover:bg-accent-500/10"
          @click="addDraw"
        >
          <span>
            <span class="block font-semibold text-accent-700">Buy with 1% Advance</span>
            <span class="block text-xs text-slate-500">
              Win the pool and it's yours · odds 1 in {{ product.open_batch?.size ?? 100 }}
            </span>
          </span>
          <span class="flex-none font-bold text-accent-700">{{ money(product.entry_price) }}</span>
        </button>

        <!-- Full price -->
        <button
          v-if="canBuy"
          class="flex w-full items-center justify-between rounded-xl border-2 border-brand-600 bg-brand-50/50 px-4 py-3 text-left transition hover:bg-brand-50"
          @click="addBuy"
        >
          <span>
            <span class="block font-semibold text-brand-800">Buy Now</span>
            <span class="block text-xs text-slate-500">Own it today — pay the full price</span>
          </span>
          <span class="flex-none font-bold text-brand-700">
            {{ money(product.listed_price * qty) }}
            <span v-if="qty > 1" class="block text-right text-[11px] font-normal text-slate-400">× {{ qty }}</span>
          </span>
        </button>

        <p v-if="alreadyBooked" class="text-center text-xs text-slate-500">A 1% booking for this item is already in your cart.</p>
        <p v-if="!canBuy && !canDraw" class="rounded-lg bg-slate-50 p-3 text-center text-sm text-slate-500">
          This product isn't available to buy right now.
        </p>
      </div>

      <div class="mt-4 flex items-center justify-between text-xs">
        <button class="text-slate-500 hover:text-slate-700" @click="closeBuyOptions">Keep shopping</button>
        <button v-if="cart.count" class="font-semibold text-brand-700 hover:underline" @click="goToCart">
          View cart ({{ cart.count }}) →
        </button>
      </div>
    </div>
  </div>
</template>
