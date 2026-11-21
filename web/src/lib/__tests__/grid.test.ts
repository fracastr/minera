import { describe, expect, it } from 'vitest'
import { parseNumber } from '../grid'

const parse = (newValue: unknown) => parseNumber({ newValue, oldValue: 7 } as never)

describe('parseNumber', () => {
  it('reads Chilean and plain numbers', () => {
    expect(parse('1.234,5')).toBe(1234.5)
    expect(parse('1234,5')).toBe(1234.5)
    expect(parse('17.900')).toBe(17900)
    expect(parse('58.66')).toBe(58.66)
    expect(parse('-632')).toBe(-632)
    expect(parse(12.5)).toBe(12.5)
  })
  it('keeps the old value on junk', () => {
    expect(parse('')).toBe(7)
    expect(parse('abc')).toBe(7)
    expect(parse('1e400')).toBe(7)
  })
})
