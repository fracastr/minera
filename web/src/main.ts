import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import './lib/theme'
import { getToken, TOKEN_KEY, watchIdle } from './lib/session'
import { router } from './router'
import { useAuthStore } from './stores/auth'
import './styles.css'

const stopIdle = watchIdle()

const app = createApp(App).use(createPinia()).use(router)
app.mount('#app')

// Logged out in another tab (key removed, or storage cleared): follow it here.
const auth = useAuthStore()
const onStorage = (e: StorageEvent) => {
  if ((e.key === TOKEN_KEY || e.key === null) && !getToken() && auth.token) {
    auth.reset()
    router.replace({ name: 'login' })
  }
}
window.addEventListener('storage', onStorage)

import.meta.hot?.dispose(() => {
  stopIdle()
  window.removeEventListener('storage', onStorage)
})
