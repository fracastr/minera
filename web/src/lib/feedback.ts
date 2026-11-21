import { reactive, ref } from 'vue'

export interface Toast {
  id: number
  tone: 'ok' | 'danger'
  message: string
}

export const toasts = reactive<Toast[]>([])
let seq = 0

/** Short-lived message; errors stay until dismissed (Toaster sets the duration). */
export function notify(tone: Toast['tone'], message: string) {
  toasts.push({ id: ++seq, tone, message })
}

export interface AskAction<T extends string> {
  value: T
  label: string
  tone?: 'primary' | 'danger' | 'quiet'
}

export interface AskRequest {
  title: string
  body: string
  actions: AskAction<string>[]
  resolve: (value: string | null) => void
}

export const pending = ref<AskRequest | null>(null)

/** Modal question with explicit answers; resolves null when dismissed. Mounted once by AskHost. */
export function ask<T extends string>(title: string, body: string, actions: AskAction<T>[]): Promise<T | null> {
  pending.value?.resolve(null)
  return new Promise(resolve => {
    pending.value = { title, body, actions, resolve: resolve as (v: string | null) => void }
  })
}

export function answer(value: string | null) {
  const p = pending.value
  pending.value = null
  p?.resolve(value)
}
