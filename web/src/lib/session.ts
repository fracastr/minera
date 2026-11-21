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
  localStorage.setItem(USER_KEY, JSON.stringify(user))
  localStorage.setItem(ACTIVITY_KEY, String(now))
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

export function touchActivity(now = Date.now()) {
  if (getToken()) localStorage.setItem(ACTIVITY_KEY, String(now))
}

export function isIdle(now = Date.now(), lifetimeMinutes = sessionLifetimeMinutes()): boolean {
  const last = Number.parseInt(localStorage.getItem(ACTIVITY_KEY) ?? '', 10)
  if (!Number.isFinite(last)) return false
  return now - last > lifetimeMinutes * 60_000
}

export function expireSession() {
  clearSession()
  if (!location.pathname.startsWith('/login')) location.assign('/login?sesion=expirada')
}

/** Ends the session after the idle lifetime: checked every minute and when the tab becomes visible. */
export function watchIdle() {
  const check = () => {
    if (getToken() && isIdle()) expireSession()
  }
  for (const evt of ['pointerdown', 'keydown', 'scroll', 'mousemove'])
    window.addEventListener(evt, () => touchActivity(), { passive: true })
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') check()
  })
  window.setInterval(check, 60_000)
  check()
}
