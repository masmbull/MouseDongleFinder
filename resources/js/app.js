import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from 'axios'

import App from './App.vue'
import router from './router'
import { useToast } from './composables/useToast'
import '../css/app.css'

// Configure axios for API calls
axios.defaults.baseURL = '/api'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.withCredentials = true

// Add CSRF token to requests
axios.interceptors.request.use((config) => {
  const token = document.querySelector('meta[name="csrf-token"]')
  if (token) {
    config.headers['X-CSRF-TOKEN'] = token.getAttribute('content')
  }
  return config
})

// Surface a readable error toast for failed API requests
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    const data = error.response?.data
    const message =
      data?.message ||
      data?.error ||
      (typeof data === 'string' ? data : null) ||
      error.message ||
      'Something went wrong. Please try again.'

    useToast().error('Request failed', String(message))

    return Promise.reject(error)
  }
)

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Global error handler
app.config.errorHandler = (err, instance, info) => {
  console.error('Vue error:', err)
  console.error('Info:', info)
  useToast().error('Unexpected error', 'Something went wrong in the interface.')
}

app.mount('#app')
