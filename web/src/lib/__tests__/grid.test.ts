import { beforeEach, describe, expect, it } from 'vitest'
import { toasts } from '../feedback'
import { parseNumber, parsePct, readNumber } from '../grid'

describe('readNumber', () => {
  it('reads Chilean and plain numbers', () => {
    expect(readNumber('1.234,5')).toBe(1234.5)
    expect(readNumber('1234,5')).toBe(1234.5)
    expect(readNumber('17.900')).toBe(17900)
    expect(readNumber('58.66')).toBe(58.66)
    expect(readNumber('-632')).toBe(-632)
    expect(readNumber(12.5)).toBe(12.5)
  })
  it('treats the dot as decimal when thousands would exceed a 0–100 column', () => {
    expect(readNumber('0.125', 100)).toBe(0.125)
    expect(readNumber('1.500', 100)).toBe(1.5)
    expect(readNumber('58.660', 100)).toBe(58.66)
    expect(readNumber('1.500')).toBe(1500)
  })
  it('rejects anything that is not a plain finite number', () => {
    for (const bad of ['', 'abc', '0x10', '1e5', '1e400', '-', '.', '12a', 'Infinity', '9'.repeat(20)]) expect(readNumber(bad)).toBeNull()
  })
})

describe('parsers', () => {
  beforeEach(() => void toasts.splice(0))
  const p = (newValue: string) => ({ newValue, oldValue: 7 })

  it('keep the old value and say why', () => {
    expect(parseNumber(p('abc'))).toBe(7)
    expect(toasts.at(-1)?.message).toContain('«abc» no es un valor válido')
    expect(parsePct(p('120'))).toBe(7)
    expect(toasts.at(-1)?.message).toContain('0 a 100')
    expect(parsePct(p('1.500'))).toBe(1.5)
  })
})
