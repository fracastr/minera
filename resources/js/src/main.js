import Vue from 'vue'
import { ToastPlugin, ModalPlugin } from 'bootstrap-vue'
import VueCompositionAPI from '@vue/composition-api'

import i18n from '@/libs/i18n'
import i18nPlugin from '@/libs/i18n/plugin'
import router from './router'
import store from './store'
import App from './App.vue'

// Global Components
import './global-components'

// 3rd party plugins
import '@axios'
import '@/libs/acl'
import '@/libs/portal-vue'
import '@/libs/clipboard'
import '@/libs/toastification'
import '@/libs/sweet-alerts'
import '@/libs/vue-select'
import '@/libs/tour'

// Axios Mock Adapter
import '@/@fake-db/db'

// BSV Plugin Registration
Vue.use(ToastPlugin)
Vue.use(ModalPlugin)

// Composition API
Vue.use(VueCompositionAPI)

// i18n Plugin
Vue.use(i18nPlugin)

// Feather font icon - For form-wizard
// * Shall remove it if not using font-icons of feather-icons - For form-wizard
require('@core/assets/fonts/feather/iconfont.css') // For form-wizard

// import core styles
require('@core/scss/core.scss')

// import assets styles
require('@/assets/scss/style.scss')

Vue.config.productionTip = false

// Function to fix vertical scrolling
function fixVerticalScroll() {
  // Force body to allow vertical scrolling
  document.body.style.overflowY = 'auto'
  document.body.style.height = 'auto'
  document.body.style.minHeight = '100vh'

  // Fix app content containers
  const appContent = document.querySelector('.app-content')
  if (appContent) {
    appContent.style.overflow = 'visible'

    const contentAreaWrapper = appContent.querySelector('.content-area-wrapper')
    if (contentAreaWrapper) {
      contentAreaWrapper.style.overflow = 'visible'
      contentAreaWrapper.style.height = 'auto'
    }

    const contentWrapper = appContent.querySelector('.content-wrapper')
    if (contentWrapper) {
      contentWrapper.style.overflow = 'visible'
      contentWrapper.style.height = 'auto'
    }

    const contentBody = appContent.querySelector('.content-body')
    if (contentBody) {
      contentBody.style.overflow = 'visible'
      contentBody.style.height = 'auto'
    }
  }

  // Fix layout containers
  const layouts = document.querySelectorAll('.vertical-layout, .horizontal-layout')
  layouts.forEach(layout => {
    layout.style.overflow = 'visible'
  })

  // Force scrollbar to be visible
  document.documentElement.style.overflowY = 'scroll'
}

const app = new Vue({
  router,
  store,
  i18n,
  render: h => h(App),
  mounted() {
    // Apply scroll fix after app is mounted
    this.$nextTick(() => {
      fixVerticalScroll()

      // Watch for route changes to reapply scroll fix
      this.$watch('$route', () => {
        this.$nextTick(() => {
          fixVerticalScroll()
        })
      })
    })
  }
}).$mount('#app')

// Also apply scroll fix on window load
window.addEventListener('load', fixVerticalScroll)

// Apply scroll fix periodically to ensure it stays
setInterval(fixVerticalScroll, 1000)
