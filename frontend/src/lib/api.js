import axios from 'axios'

// Same-origin (Vite proxy) → cookies just work. Sanctum SPA cookie auth.
const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true, // send X-XSRF-TOKEN from the XSRF-TOKEN cookie
  headers: { Accept: 'application/json' },
})

// Prime the CSRF cookie once at boot; call again if a request 419s.
export function csrf() {
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

export default api
