import { watch } from 'vue'

/** Resolves once `cond` (reactive) is truthy; rejects with `error` after `ms`. */
export function hasta(cond: () => unknown, ms = 20_000, error = 'Se agotó el tiempo de espera.'): Promise<void> {
  return new Promise((resolve, reject) => {
    if (cond()) return resolve()
    const timer = setTimeout(() => {
      stop()
      reject(new Error(error))
    }, ms)
    const stop = watch(cond, v => {
      if (!v) return
      clearTimeout(timer)
      stop()
      resolve()
    })
  })
}
