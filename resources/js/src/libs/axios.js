import Vue from 'vue'
import axios from 'axios'
import router from '@/router'

// axios
const axiosIns = axios.create({
  // Configuración base
  baseURL: process.env.NODE_ENV === 'production'
    ? 'http://34.229.82.49:80/'
    : 'http://localhost:8000/',
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  },
  timeout: 30000, // 30 segundos
})

// Interceptor de request - Se ejecuta ANTES de cada petición
axiosIns.interceptors.request.use(
  config => {
    // Agregar token si existe
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // Agregar CSRF token para Laravel
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    if (csrfToken) {
      config.headers['X-CSRF-TOKEN'] = csrfToken
    }

    // Log de peticiones en desarrollo
    if (process.env.NODE_ENV === 'development') {
      console.log(`🚀 ${config.method?.toUpperCase()} ${config.url}`, config.data || '')
    }

    return config
  },
  error => {
    console.error('❌ Error en request interceptor:', error)
    return Promise.reject(error)
  }
)

// Interceptor de response - Se ejecuta DESPUÉS de cada respuesta
axiosIns.interceptors.response.use(
  response => {
    // Log de respuestas exitosas en desarrollo
    if (process.env.NODE_ENV === 'development') {
      console.log(`✅ ${response.config.method?.toUpperCase()} ${response.config.url}`, response.data)
    }
    return response
  },
  error => {
    // Log de errores
    console.error('❌ Error en response interceptor:', error)

    // Manejo global de errores
    if (error.response) {
      const { status, data } = error.response

      switch (status) {
        case 401: // No autorizado
          console.log('🔒 Usuario no autorizado, redirigiendo a login...')
          localStorage.removeItem('token')
          localStorage.removeItem('userData')
          router.push('/login')
          break

        case 403: // Prohibido
          console.log('🚫 Acceso prohibido')
          break

        case 404: // No encontrado
          console.log('🔍 Recurso no encontrado')
          break

        case 422: // Error de validación
          console.log('⚠️ Error de validación:', data.errors)
          break

        case 500: // Error del servidor
          console.log('💥 Error interno del servidor')
          break

        default:
          console.log(`❌ Error ${status}:`, data)
      }
    } else if (error.request) {
      // La petición se hizo pero no se recibió respuesta
      console.error('🌐 No se pudo conectar con el servidor')
    } else {
      // Error al configurar la petición
      console.error('⚙️ Error al configurar la petición:', error.message)
    }

    return Promise.reject(error)
  }
)

// Hacer axiosIns disponible globalmente en Vue
Vue.prototype.$http = axiosIns

export default axiosIns
