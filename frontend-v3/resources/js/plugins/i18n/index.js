import { createI18n } from 'vue-i18n'
import { cookieRef } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

import { en as vuetifyEn, es as vuetifyEs } from 'vuetify/locale'

// Vuetify's own strings (table footers, pickers…) live under $vuetify so they follow the app language.
const vuetifyMessages = { es: vuetifyEs, en: vuetifyEn }

const messages = Object.fromEntries(Object.entries(import.meta.glob('./locales/*.json', { eager: true }))
  .map(([key, value]) => {
    const lang = key.slice(10, -5)

    return [lang, { ...value.default, $vuetify: vuetifyMessages[lang] }]
  }))

let _i18n = null
export const getI18n = () => {
  if (_i18n === null) {
    _i18n = createI18n({
      legacy: false,
      locale: cookieRef('language', themeConfig.app.i18n.defaultLocale).value,
      fallbackLocale: 'en',
      messages,
    })
  }
  
  return _i18n
}
export default function (app) {
  app.use(getI18n())
}
