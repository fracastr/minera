import { AUTH_TOKEN_KEY } from '@/auth/config'

const LAST_ACTIVITY_KEY = 'sondekLastActivityAt'

export function getSessionLifetimeMinutes() {
  const meta = document.querySelector('meta[name="session-lifetime"]')
  const parsed = meta ? parseInt(meta.getAttribute('content'), 10) : NaN
  return Number.isFinite(parsed) && parsed > 0 ? parsed : 120
}

export function touchSessionActivity() {
  localStorage.setItem(LAST_ACTIVITY_KEY, String(Date.now()))
}

export function clearSession() {
  localStorage.removeItem(AUTH_TOKEN_KEY)
  localStorage.removeItem('userData')
  localStorage.removeItem(LAST_ACTIVITY_KEY)
}

export function isClientSessionIdle() {
  const raw = localStorage.getItem(LAST_ACTIVITY_KEY)
  if (!raw) return false
  const last = parseInt(raw, 10)
  if (!Number.isFinite(last)) return false
  const limitMs = getSessionLifetimeMinutes() * 60 * 1000
  return Date.now() - last > limitMs
}

export async function validateSessionWithServer() {
  const token = localStorage.getItem(AUTH_TOKEN_KEY)
  if (!token) return false
  if (isClientSessionIdle()) {
    clearSession()
    return false
  }
  try {
    const axios = (await import('@axios')).default
    await axios.get('/api/auth/user')
    touchSessionActivity()
    return true
  } catch (e) {
    if (e.response?.status === 401) {
      clearSession()
    }
    return false
  }
}

function redirectToLoginAfterExpiry() {
  clearSession()
  const path = window.location.pathname
  if (path === '/login' || path.startsWith('/login')) return
  window.location.assign('/login?sesion=expirada')
}

let idleListenersBound = false

export function bindSessionIdleWatch() {
  if (idleListenersBound || typeof window === 'undefined') return
  idleListenersBound = true

  const bump = () => {
    if (localStorage.getItem(AUTH_TOKEN_KEY)) touchSessionActivity()
  }

  ;['click', 'keydown', 'scroll', 'mousemove'].forEach(evt => {
    window.addEventListener(evt, bump, { passive: true })
  })

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && localStorage.getItem(AUTH_TOKEN_KEY)) {
      if (isClientSessionIdle()) {
        redirectToLoginAfterExpiry()
        return
      }
      validateSessionWithServer()
    }
  })

  window.setInterval(() => {
    if (!localStorage.getItem(AUTH_TOKEN_KEY)) return
    if (isClientSessionIdle()) {
      redirectToLoginAfterExpiry()
      return
    }
    if (!localStorage.getItem(LAST_ACTIVITY_KEY)) {
      validateSessionWithServer()
    }
  }, 60 * 1000)
}
