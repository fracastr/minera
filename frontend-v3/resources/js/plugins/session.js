let bound = false

export default function () {
  if (bound || typeof window === 'undefined')
    return
  bound = true

  const bump = () => hasSession() && touchSession()
  for (const evt of ['click', 'keydown', 'scroll', 'mousemove'])
    window.addEventListener(evt, bump, { passive: true })

  const check = () => {
    if (hasSession() && isSessionIdle())
      expireSession()
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible' || !hasSession())
      return
    if (isSessionIdle())
      return expireSession()

    // Back on the tab: confirm the token is still valid (401 → $api expires the session).
    $api('/auth/user').catch(() => {})
  })
  window.setInterval(check, 60 * 1000)
  check()
}
