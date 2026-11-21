import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ACTIVITY_KEY, clearSession, expireSession, getStoredUser, getToken, isIdle, saveSession, touchActivity, USER_KEY, watchIdle } from '../session'

const user = { id: 1, fullName: 'Ana', email: 'ana@cmp.cl', role: 'viewer' as const, ability: [] }
const MIN = 60_000

describe('session storage', () => {
  beforeEach(() => localStorage.clear())

  it('saves and clears token, user and activity under cmp.* keys', () => {
    saveSession('tok', user, 1000)
    expect(getToken()).toBe('tok')
    expect(getStoredUser()).toEqual(user)
    expect(localStorage.getItem(ACTIVITY_KEY)).toBe('1000')
    clearSession()
    expect(getToken()).toBeNull()
    expect(getStoredUser()).toBeNull()
    expect(localStorage.getItem(ACTIVITY_KEY)).toBeNull()
  })

  it('treats a corrupt stored user as absent', () => {
    localStorage.setItem(USER_KEY, '{nope')
    expect(getStoredUser()).toBeNull()
  })

  it('only records activity while logged in', () => {
    touchActivity(5)
    expect(localStorage.getItem(ACTIVITY_KEY)).toBeNull()
  })
})

describe('isIdle', () => {
  beforeEach(() => localStorage.clear())

  it('is idle only after the lifetime has passed since the last activity', () => {
    saveSession('tok', user, 0)
    expect(isIdle(120 * MIN, 120)).toBe(false)
    expect(isIdle(120 * MIN + 1, 120)).toBe(true)
    touchActivity(100 * MIN)
    expect(isIdle(200 * MIN, 120)).toBe(false)
  })

  it('is never idle without a token', () => {
    expect(isIdle(Date.now(), 120)).toBe(false)
  })

  it('starts counting when a token has no recorded activity', () => {
    saveSession('tok', user, 0)
    localStorage.removeItem(ACTIVITY_KEY)
    expect(isIdle(50 * MIN, 120)).toBe(false)
    expect(localStorage.getItem(ACTIVITY_KEY)).toBe(String(50 * MIN))
    expect(isIdle(171 * MIN, 120)).toBe(true)
  })

  it('reads the lifetime from the session-lifetime meta tag, defaulting to 120', () => {
    saveSession('tok', user, 0)
    expect(isIdle(121 * MIN)).toBe(true)
    const meta = document.createElement('meta')
    meta.name = 'session-lifetime'
    meta.content = '240'
    document.head.append(meta)
    expect(isIdle(121 * MIN)).toBe(false)
    meta.remove()
  })
})

describe('touchActivity', () => {
  beforeEach(() => localStorage.clear())

  it('writes at most once every 15 s', () => {
    saveSession('tok', user, 0)
    touchActivity(10_000)
    expect(localStorage.getItem(ACTIVITY_KEY)).toBe('0')
    touchActivity(15_000)
    expect(localStorage.getItem(ACTIVITY_KEY)).toBe('15000')
  })
})

describe('expireSession', () => {
  beforeEach(() => localStorage.clear())

  it('revokes the token on the server and clears storage', () => {
    const fetchMock = vi.fn(() => Promise.resolve(new Response()))
    vi.stubGlobal('fetch', fetchMock)
    history.replaceState(null, '', '/login') // stay put: jsdom cannot navigate
    saveSession('tok', user, 0)

    expireSession()

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/logout', expect.objectContaining({
      method: 'POST',
      headers: expect.objectContaining({ Authorization: 'Bearer tok' }),
    }))
    expect(getToken()).toBeNull()
    vi.unstubAllGlobals()
  })
})

describe('watchIdle', () => {
  it('returns a disposer that removes its listeners and timer', () => {
    const add = vi.spyOn(window, 'addEventListener')
    const remove = vi.spyOn(window, 'removeEventListener')
    const stop = watchIdle()
    const added = add.mock.calls.map(c => c[0])
    expect(added).toEqual(['pointerdown', 'keydown', 'scroll'])
    stop()
    expect(remove.mock.calls.map(c => c[0])).toEqual(added)
    vi.restoreAllMocks()
  })
})
