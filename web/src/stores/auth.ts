import { FetchError } from 'ofetch'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/lib/api'
import { clearSession, getStoredUser, getToken, saveSession, saveUser, type UserData } from '@/lib/session'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(getToken())
  const user = ref<UserData | null>(getStoredUser())
  const validated = ref(false)
  const loggedIn = computed(() => !!token.value && !!user.value)

  async function login(email: string, password: string) {
    const res = await api<{ accessToken: string; userData: UserData }>('/auth/login', {
      method: 'POST',
      body: { email, password },
    })
    saveSession(res.accessToken, res.userData)
    token.value = res.accessToken
    user.value = res.userData
    validated.value = true
  }

  /** Checks the stored token once per app load and refreshes role/abilities. Only a 401 ends the session. */
  async function validate(): Promise<boolean> {
    try {
      const fresh = await api<UserData>('/auth/user')
      saveUser(fresh)
      user.value = fresh
    } catch (e) {
      if (e instanceof FetchError && e.status === 401) {
        reset()
        return false
      }
    }
    validated.value = true
    return true
  }

  async function logout() {
    try {
      await api('/auth/logout', { method: 'POST' })
    } catch {} // the token is dropped locally either way
    reset()
  }

  function reset() {
    clearSession()
    token.value = null
    user.value = null
    validated.value = false
  }

  return { token, user, validated, loggedIn, login, validate, logout, reset }
})
