import { reactive, ref } from 'vue'

export interface Toast {
  id: number
  tone: 'ok' | 'danger'
  message: string
  /** Optional link shown under the message (e.g. the Drive file when the popup was blocked). */
  link?: { href: string; label: string }
}

export const toasts = reactive<Toast[]>([])
let seq = 0

/** Success messages close by themselves; errors stay until dismissed (Feedback.vue sets the duration). */
export function notify(tone: Toast['tone'], message: string, link?: Toast['link']) {
  toasts.push({ id: ++seq, tone, message, link })
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
