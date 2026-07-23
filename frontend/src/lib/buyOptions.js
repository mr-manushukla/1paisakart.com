import { ref } from 'vue'

// One shared modal for the whole app, rather than one per product card.
export const buyOptionsProduct = ref(null)

export function openBuyOptions(product) { buyOptionsProduct.value = product }
export function closeBuyOptions() { buyOptionsProduct.value = null }
