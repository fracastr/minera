import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  headers: { Accept: 'application/json' },
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value
    if (accessToken)
      options.headers.append('Authorization', `Bearer ${accessToken}`)
  },
  onResponse({ response }) {
    if (response.ok && hasSession())
      touchSession()
  },
  // Sanctum tokens expire after idle time: drop the session and go back to login.
  onResponseError({ response }) {
    if (response.status === 401 && hasSession())
      expireSession()
  },
})
