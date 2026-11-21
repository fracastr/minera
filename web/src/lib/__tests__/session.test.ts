import { beforeEach, describe, expect, it } from 'vitest'
import { ACTIVITY_KEY, clearSession, getStoredUser, getToken, isIdle, saveSession, touchActivity, USER_KEY } from '../session'

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

  it('is not idle without a recorded activity', () => {
    expect(isIdle(Date.now(), 120)).toBe(false)
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
