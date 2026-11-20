// Client-side idle logout, same rules as the Vue 2 app (resources/js/src/auth/session.js):
// the server's SESSION_LIFETIME (meta tag) is the idle limit; any activity or API call renews it.
const LAST_ACTIVITY_KEY = 'sondekV3LastActivityAt'

export const sessionLifetimeMinutes = () => {
  const parsed = Number.parseInt(document.querySelector('meta[name="session-lifetime"]')?.content, 10)

  return Number.isFinite(parsed) && parsed > 0 ? parsed : 120
}

export const hasSession = () => !!useCookie('accessToken').value

export const touchSession = () => {
  try {
    localStorage.setItem(LAST_ACTIVITY_KEY, String(Date.now()))
  }
  catch (e) {
    // Storage blocked: the server-side token expiry still applies.
  }
}

export const isSessionIdle = () => {
  let last = Number.NaN
  try {
    last = Number.parseInt(localStorage.getItem(LAST_ACTIVITY_KEY), 10)
  }
  catch (e) {
    return false
  }

  return Number.isFinite(last) && Date.now() - last > sessionLifetimeMinutes() * 60 * 1000
}

export const clearSession = () => {
  useCookie('accessToken').value = null
  useCookie('userData').value = null
  useCookie('userAbilityRules').value = null
  try {
    localStorage.removeItem(LAST_ACTIVITY_KEY)
  }
  catch (e) {
    // ignore
  }
}

export const expireSession = () => {
  clearSession()
  if (!window.location.pathname.startsWith('/v3/login'))
    window.location.assign('/v3/login?sesion=expirada')
}
