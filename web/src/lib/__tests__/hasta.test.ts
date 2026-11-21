import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { hasta } from '../hasta'

describe('hasta', () => {
  it('resolves now, or when the condition turns true', async () => {
    await hasta(() => true)
    const x = ref(false)
    const p = hasta(() => x.value)
    x.value = true
    await expect(p).resolves.toBeUndefined()
  })

  it('rejects after the timeout', async () => {
    vi.useFakeTimers()
    const p = hasta(() => false, 100, 'tarde')
    vi.advanceTimersByTime(100)
    await expect(p).rejects.toThrow('tarde')
    vi.useRealTimers()
  })
})
