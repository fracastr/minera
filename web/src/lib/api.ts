import { FetchError, ofetch } from 'ofetch'
import { expireSession, getToken } from './session'

export const api = ofetch.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
  onRequest({ options }) {
    const token = getToken()
    if (token) options.headers.set('Authorization', `Bearer ${token}`)
  },
  onResponseError({ response }) {
    // A 401 with a token means it expired or was revoked; without one it is just a failed login.
    if (response.status === 401 && getToken()) expireSession()
  },
})

/** Readable Spanish message for a failed request. */
export function errorMessage(error: unknown, fallback = 'Ocurrió un error inesperado. Intenta nuevamente.'): string {
  if (!(error instanceof FetchError)) return fallback
  if (!error.response) return 'No se pudo conectar con el servidor. Revisa tu conexión.'
  const message = error.data?.message
  return typeof message === 'string' && message ? message : fallback
}

/** First validation error for a field (Laravel 422 shape). */
export function fieldError(error: unknown, field: string): string | undefined {
  return error instanceof FetchError ? error.data?.errors?.[field]?.[0] : undefined
}
