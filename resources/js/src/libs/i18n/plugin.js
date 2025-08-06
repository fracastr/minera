import { getSavedLocale, saveLocale } from './utils'

export default {
  install(Vue, options) {
    // Agregar métodos globales para manejar el idioma
    Vue.prototype.$setLocale = function(locale) {
      this.$i18n.locale = locale
      saveLocale(locale)
    }

    Vue.prototype.$getSavedLocale = function() {
      return getSavedLocale()
    }

    // Inicializar el idioma guardado al cargar la aplicación
    Vue.mixin({
      created() {
        if (this.$i18n) {
          const savedLocale = getSavedLocale()
          if (savedLocale && this.$i18n.availableLocales.includes(savedLocale)) {
            this.$i18n.locale = savedLocale
          }
        }
      }
    })
  }
}
