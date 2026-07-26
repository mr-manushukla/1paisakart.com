import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/', name: 'home', component: () => import('../pages/Home.vue') },
  { path: '/shop', name: 'shop', component: () => import('../pages/Shop.vue') },
  { path: '/product/:slug', name: 'product', component: () => import('../pages/ProductDetail.vue') },
  { path: '/winners', name: 'winners', component: () => import('../pages/Winners.vue') },
  { path: '/cart', name: 'cart', component: () => import('../pages/Cart.vue') },
  { path: '/wishlist', name: 'wishlist', component: () => import('../pages/Wishlist.vue') },
  { path: '/login', name: 'login', component: () => import('../pages/Login.vue'), meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: () => import('../pages/Register.vue'), meta: { guestOnly: true } },

  { path: '/checkout', name: 'checkout', component: () => import('../pages/Checkout.vue'), meta: { auth: true, role: 'customer' } },
  { path: '/wallet', name: 'wallet', component: () => import('../pages/Wallet.vue'), meta: { auth: true, role: 'customer' } },
  { path: '/profile', name: 'profile', component: () => import('../pages/Profile.vue'), meta: { auth: true } },
  { path: '/addresses', name: 'addresses', component: () => import('../pages/Addresses.vue'), meta: { auth: true, role: 'customer' } },
  { path: '/orders', name: 'orders', component: () => import('../pages/Orders.vue'), meta: { auth: true, role: 'customer' } },
  { path: '/my-draws', name: 'my-draws', component: () => import('../pages/MyDraws.vue'), meta: { auth: true, role: 'customer' } },

  { path: '/vendor', name: 'vendor', component: () => import('../pages/VendorDashboard.vue'), meta: { auth: true, role: 'vendor' } },
  { path: '/admin', name: 'admin', component: () => import('../pages/AdminDashboard.vue'), meta: { auth: true, role: 'admin' } },

  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.guestOnly && auth.isAuthed) return { name: auth.homeRouteName ?? 'home' }
  if (to.meta.auth && !auth.isAuthed) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.role && auth.role !== to.meta.role) return { name: 'home' }
  return true
})

export default router
