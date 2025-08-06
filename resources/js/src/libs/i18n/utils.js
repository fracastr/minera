// Utilidades para manejar el localStorage de forma segura
export const localeStorage = {
  get(key, defaultValue = null) {
    try {
      const item = localStorage.getItem(key)
      return item !== null ? item : defaultValue
    } catch (error) {
      console.warn('Error reading from localStorage:', error)
      return defaultValue
    }
  },

  set(key, value) {
    try {
      localStorage.setItem(key, value)
      return true
    } catch (error) {
      console.warn('Error writing to localStorage:', error)
      return false
    }
  },

  remove(key) {
    try {
      localStorage.removeItem(key)
      return true
    } catch (error) {
      console.warn('Error removing from localStorage:', error)
      return false
    }
  }
}

// Función para obtener el idioma guardado
export function getSavedLocale() {
  return localeStorage.get('selectedLocale', 'es')
}

// Función para guardar el idioma
export function saveLocale(locale) {
  return localeStorage.set('selectedLocale', locale)
}
