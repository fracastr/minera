import authConfig from '@/auth/config'

// Sanctum: no se registran interceptores JWT del template Vuexy.
// Solo se exporta la config de almacenamiento del token para compatibilidad.
export default {
  jwtConfig: authConfig,
}
