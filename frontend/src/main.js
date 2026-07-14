import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { csrf } from './lib/api'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
app.use(createPinia())
app.use(router)

// Prime CSRF cookie + hydrate the session, then mount (avoids an auth flash).
const auth = useAuthStore()
Promise.allSettled([csrf(), auth.fetchMe()]).finally(() => app.mount('#app'))
