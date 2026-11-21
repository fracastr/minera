import { describe, expect, it } from 'vitest'
import { caudalNodo, claveTendencia, flujoDe, sidakZ, sugerencia, toleranciasAmplias } from '../diagnostico'
import type { Flujo, Sesion } from '../types'

const v = (medido: number, tol: number) => ({ medido, reconciliado: medido, tol_inf: tol, tol_sup: tol, desv_inf: 0, desv_sup: 0 })
const flujo = (idx: number, nombre: string, tms: number, tol: number, tag: string | null = null): Flujo =>
  ({ idx, nombre, tag, descripcion: null, jerarquia: 1, stock: false, tms: v(tms, tol), leyes: [v(60, 2)] })

describe('diagnostico', () => {
  it('matches the Šidák critical value of diagnostico.py', () => {
    expect(sidakZ(1)).toBeCloseTo(1.959964, 5)
    expect(sidakZ(10)).toBeCloseTo(2.8000, 3)
  })

  it('flags tolerances too wide to see a 25 % error, using the 5 % default for tol 0', () => {
    const s: Pick<Sesion, 'flujos' | 'componentes' | 'matriz'> = {
      componentes: ['FeT'],
      flujos: [flujo(0, 'A', 100, 30), flujo(1, 'B', 100, 0), flujo(2, 'C', 100, 10), flujo(3, 'Ratio', 5, 50)],
      matriz: [[1, -1, -1, 0]], // Ratio is in no balance: Flask does not test it either
    }
    const r = toleranciasAmplias(s)
    expect(r.tolMax).toBeCloseTo((25 * 1.96) / sidakZ(3), 6)
    expect(r.flujos.map(f => [f.flujo, f.variable, f.tolPct])).toEqual([['A', 'TMS', 30]])
  })

  it('keys the trend like the server: name|tag', () => {
    expect(claveTendencia({ flujo: 'X', tag: 'T1' })).toBe('X|T1')
    expect(claveTendencia({ flujo: 'X', tag: null })).toBe('X|')
  })

  it('finds the session row by name and tag', () => {
    const fs = [flujo(0, 'X', 1, 1, 'T1'), flujo(1, 'X', 1, 1, 'T2')]
    expect(flujoDe(fs, 'X', 'T2')?.idx).toBe(1)
    expect(flujoDe(fs, 'X', 'T9')?.idx).toBe(0)
    expect(flujoDe(fs, 'Y')).toBeUndefined()
  })

  it('writes a value keeping the stock sign, or widens both tolerances to the correction', () => {
    const f = flujo(0, 'S', -130, 5)
    expect(sugerencia(f, 'tms', 'corregir_valor', 100)).toEqual({ campo: 'medido', valor: -100 })
    expect(sugerencia(f, 'tms', 'ampliar_tolerancia', 121)).toEqual({ campo: 'tolerancia', valor: 7 })
    expect(sugerencia(f, 'ley_1', 'revisar_medicion', 61)).toEqual({ campo: 'medido', valor: 61 })
  })

  it('computes node throughput from measured TMS', () => {
    const s = { flujos: [flujo(0, 'A', 100, 5), flujo(1, 'B', -90, 5)], matriz: [[1, -1]], nodos: [{ idx: 0, nombre: 'N', residuo_tms: 0, residuo_finos: [] }] }
    expect(caudalNodo(s, 'N')).toBe(95)
    expect(caudalNodo(s, 'Z')).toBeNull()
  })
})
