import { expect, it } from 'vitest'
import { passwordRules } from '../passwordRules'

const passes = (p: string) => passwordRules.map(r => r.test(p))

it('checks each StrongPassword rule independently', () => {
  expect(passes('')).toEqual([false, false, false, false, false])
  expect(passes('abcdefghijkl')).toEqual([true, false, true, false, false])
  expect(passes('Valle-Huasco-2026')).toEqual([true, true, true, true, true])
})
