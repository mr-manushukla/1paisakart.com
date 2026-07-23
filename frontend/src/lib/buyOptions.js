import { ref } from 'vue'

// One shared modal for the whole app, rather than one per product card.
export const buyOptionsProduct = ref(null)
// Product pages have a quantity stepper; cards don't. A 1% booking is always
// one seat, so this only ever applies to the full-price option.
export const buyOptionsQty = ref(1)

export function openBuyOptions(product, qty = 1) {
  buyOptionsProduct.value = product
  buyOptionsQty.value = Math.max(1, Number(qty) || 1)
}
export function closeBuyOptions() {
  buyOptionsProduct.value = null
  buyOptionsQty.value = 1
}
