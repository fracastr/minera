import type { Ability } from './abilities'

export interface UserData {
  id: number
  fullName: string
  email: string
  role: 'admin' | 'operator' | 'viewer'
  ability: Ability[]
}

// Different keys from the v1 app (accessToken / userData) so both can coexist on one origin.
export const TOKEN_KEY = 'cmp.token'
export const USER_KEY = 'cmp.user'
export const ACTIVITY_KEY = 'cmp.lastActivity'

export const getToken = () => localStorage.getItem(TOKEN_KEY)

export function getStoredUser(): UserData | null {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY) ?? 'null')
  } catch {
    return null
  }
}

export function saveSession(token: string, user: UserData, now = Date.now()) {
  localStorage.setItem(TOKEN_KEY, token)
  saveUser(user)
  localStorage.setItem(ACTIVITY_KEY, String(now))
}

export function saveUser(user: UserData) {
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

export function clearSession() {
  for (const key of [TOKEN_KEY, USER_KEY, ACTIVITY_KEY]) localStorage.removeItem(key)
}

/** Minutes of inactivity before the session ends; the server sends config('session.lifetime') in a meta tag. */
export function sessionLifetimeMinutes(): number {
  const raw = document.querySelector('meta[name="session-lifetime"]')?.getAttribute('content')
  const parsed = Number.parseInt(raw ?? '', 10)
  return parsed > 0 ? parsed : 120
}

// Activity is written at most this often: localStorage writes are synchronous.
export const ACTIVITY_THROTTLE_MS = 15_000

export function touchActivity(now = Date.now()) {
  if (!getToken()) return
  const last = Number.parseInt(localStorage.getItem(ACTIVITY_KEY) ?? '', 10)
  if (Number.isFinite(last) && now - last >= 0 && now - last < ACTIVITY_THROTTLE_MS) return
  localStorage.setItem(ACTIVITY_KEY, String(now))
}

export function isIdle(now = Date.now(), lifetimeMinutes = sessionLifetimeMinutes()): boolean {
  if (!getToken()) return false
  const last = Number.parseInt(localStorage.getItem(ACTIVITY_KEY) ?? '', 10)
  if (!Number.isFinite(last)) {
    // Token without a timestamp (e.g. key cleared): start counting now instead of never expiring.
    localStorage.setItem(ACTIVITY_KEY, String(now))
    return false
  }
  return now - last > lifetimeMinutes * 60_000
}

/** Ends the session: revokes the token on the server (best effort), clears storage, goes to login. */
export function expireSession() {
  const token = getToken()
  if (token) {
    fetch('/api/auth/logout', {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      keepalive: true,
    }).catch(() => {})
  }
  clearSession()
  if (!location.pathname.startsWith('/login')) location.assign('/login?sesion=expirada')
}

/**
 * Ends the session after the idle lifetime: checked every minute and when the tab becomes visible.
 * Returns a disposer (used on HMR).
 */
export function watchIdle(): () => void {
  const check = () => {
    if (isIdle()) expireSession()
  }
  const bump = () => touchActivity()
  const onVisible = () => {
    if (document.visibilityState !== 'visible') return
    check()
    bump()
  }
  const events = ['pointerdown', 'keydown', 'scroll'] as const
  for (const evt of events) window.addEventListener(evt, bump, { passive: true })
  document.addEventListener('visibilitychange', onVisible)
  const timer = window.setInterval(check, 60_000)
  check()

  return () => {
    for (const evt of events) window.removeEventListener(evt, bump)
    document.removeEventListener('visibilitychange', onVisible)
    window.clearInterval(timer)
  }
}
