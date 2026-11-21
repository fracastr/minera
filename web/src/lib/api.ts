import { FetchError, ofetch } from 'ofetch'
import { expireSession, getToken } from './session'

export const api = ofetch.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
  onRequest({ options }) {
    const token = getToken()
    if (token) options.headers.set('Authorization', `Bearer ${token}`)
  },
  onResponseError({ request, response }) {
    // A 401 with a token means it expired or was revoked; without one it is just a failed login.
    // The auth store handles its own /auth/user and /auth/logout calls.
    const url = typeof request === 'string' ? request : request.url
    if (response.status === 401 && getToken() && !/\/auth\/(user|logout)$/.test(url)) expireSession()
  },
})

/** Readable Spanish message for a failed request. */
export function errorMessage(error: unknown, fallback = 'Ocurrió un error inesperado. Intenta nuevamente.'): string {
  if (!(error instanceof FetchError)) return fallback
  if (!error.response) return 'No se pudo conectar con el servidor. Revisa tu conexión.'
  const message = error.data?.message
  return typeof message === 'string' && message ? message : fallback
}

/**
 * Multipart POST with upload progress (fetch cannot report it). Rejects with an Error carrying the server's message.
 */
export function upload<T>(path: string, body: FormData, onProgress: (fraction: number) => void): Promise<T> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', `/api${path}`)
    xhr.setRequestHeader('Accept', 'application/json')
    const token = getToken()
    if (token) xhr.setRequestHeader('Authorization', `Bearer ${token}`)
    xhr.upload.onprogress = e => e.lengthComputable && onProgress(e.loaded / e.total)
    xhr.onerror = () => reject(new Error('No se pudo conectar con el servidor. Revisa tu conexión.'))
    xhr.onload = () => {
      let data: { message?: string; errors?: Record<string, string[]> } | null = null
      try {
        data = JSON.parse(xhr.responseText)
      } catch {}
      if (xhr.status >= 200 && xhr.status < 300) return resolve(data as T)
      if (xhr.status === 401 && token) expireSession()
      const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
      reject(new Error(first ?? data?.message ?? 'No se pudo importar el archivo.'))
    }
    xhr.send(body)
  })
}

/** First validation error for a field (Laravel 422 shape). */
export function fieldError(error: unknown, field: string): string | undefined {
  return error instanceof FetchError ? error.data?.errors?.[field]?.[0] : undefined
}
