import { FetchError } from 'ofetch'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/lib/api'
import { clearSession, getStoredUser, getToken, saveSession, type UserData } from '@/lib/session'

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

  /** Checks the stored token with the server once per app load. Only a 401 ends the session. */
  async function validate(): Promise<boolean> {
    try {
      await api('/auth/user')
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

  return { token, user, validated, loggedIn, login, validate, logout }
})
