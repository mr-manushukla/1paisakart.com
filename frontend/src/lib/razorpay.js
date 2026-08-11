import api from './api'

const SDK = 'https://checkout.razorpay.com/v1/checkout.js'
let sdkPromise = null

/** Load Razorpay's checkout script once, on demand. */
function loadSdk() {
  if (window.Razorpay) return Promise.resolve()
  if (sdkPromise) return sdkPromise
  sdkPromise = new Promise((resolve, reject) => {
    const s = document.createElement('script')
    s.src = SDK
    s.onload = resolve
    s.onerror = () => { sdkPromise = null; reject(new Error('Could not load the payment gateway.')) }
    document.head.appendChild(s)
  })
  return sdkPromise
}

/**
 * Run a payment end to end.
 *  - the server prices the intent (never the browser)
 *  - Razorpay collects the money
 *  - the server verifies the signature and only then fulfils the order/booking
 *
 * @param {{intent:'checkout'|'draw'|'balance'|'claim'} & Record<string, any>} intent
 * @returns {Promise<object>} the fulfilment result, or null if the user dismissed checkout
 */
export async function payAndFulfil(intent) {
  await loadSdk()
  const { data: order } = await api.post('/payments/order', intent)

  return new Promise((resolve, reject) => {
    const rzp = new window.Razorpay({
      key: order.key,
      order_id: order.razorpay_order_id,
      amount: order.amount,
      currency: order.currency,
      name: '1paisakart',
      description: order.description,
      prefill: order.prefill,
      theme: { color: '#059669' },
      handler: async (response) => {
        try {
          const { data } = await api.post('/payments/verify', {
            razorpay_order_id: response.razorpay_order_id,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_signature: response.razorpay_signature,
          })
          resolve(data)
        } catch (e) {
          reject(e)
        }
      },
      modal: { ondismiss: () => resolve(null) }, // user closed checkout — not an error
    })
    rzp.on('payment.failed', (e) => reject(new Error(e?.error?.description || 'Payment failed.')))
    rzp.open()
  })
}
