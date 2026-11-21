import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import './lib/theme'
import { watchIdle } from './lib/session'
import { router } from './router'
import './styles.css'

watchIdle()

createApp(App).use(createPinia()).use(router).mount('#app')
