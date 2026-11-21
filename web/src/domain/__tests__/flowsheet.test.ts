import { describe, expect, it } from 'vitest'
import { buildFlowsheet, desbalances, layoutKey, valores } from '../flowsheet'
import type { Flujo, Variable } from '../types'

const v = (medido: number, reconciliado = medido, tol = 5): Variable => ({ medido, reconciliado, tol_inf: tol, tol_sup: tol, desv_inf: 0, desv_sup: 0 })
const flujo = (idx: number, tms: Variable, ley = v(60), extra: Partial<Flujo> = {}): Flujo => ({
  idx, nombre: `F${idx}`, tag: null, descripcion: null, jerarquia: 1, stock: false, tms, leyes: [ley], ...extra,
})
const nodos = (n: number) => Array.from({ length: n }, (_, idx) => ({ idx, nombre: `N${idx}`, residuo_tms: 0, residuo_finos: [0] }))

describe('buildFlowsheet', () => {
  // F0 feed → N0 → F1 → N1 → F2 product; F3 stock delta drawn down into N1 (stored coefficient 0).
  const flujos = [flujo(0, v(100)), flujo(1, v(100)), flujo(2, v(130)), flujo(3, v(-30), v(50), { stock: true })]
  const matriz = [[1, -1, 0, 0], [0, 1, -1, 0]]
  const fs = buildFlowsheet({ flujos, nodos: nodos(2), matriz, indices_stock: [[2, 4]] })

  it('connects units and puts boundary terminals on single-ended streams', () => {
    const edge = (j: number) => fs.edges.find(e => e.flujo === j)!
    expect(edge(0)).toMatchObject({ source: 'entrada:0', target: 'n:0', primary: true })
    expect(edge(1)).toMatchObject({ source: 'n:0', target: 'n:1' })
    expect(edge(2)).toMatchObject({ source: 'n:1', target: 'salida:2' })
    expect(fs.nodes.map(n => n.tipo)).toEqual(['unidad', 'unidad', 'entrada', 'salida', 'stock'])
  })

  it('resolves a stock delta from indices_stock and its sign', () => {
    expect(fs.edges.find(e => e.flujo === 3)).toMatchObject({ source: 'stock:3', target: 'n:1', stock: true })
    expect(fs.coef[1]![3]).toBe(1)
    // Accumulation (positive delta) leaves the node toward the inventory.
    const acc = buildFlowsheet({ flujos: [flujo(0, v(5), v(1), { stock: true })], nodos: nodos(1), matriz: [[0]], indices_stock: [[1, 1]] })
    expect(acc.edges[0]).toMatchObject({ source: 'n:0', target: 'stock:0' })
  })

  it('draws a stream with more than two ends from each source to each destination, one label', () => {
    const m = buildFlowsheet({ flujos: [flujo(0, v(10))], nodos: nodos(3), matriz: [[-1], [1], [1]], indices_stock: [] })
    expect(m.edges.map(e => [e.source, e.target, e.primary])).toEqual([['n:0', 'n:1', true], ['n:0', 'n:2', false]])
    expect(new Set(m.edges.map(e => e.id)).size).toBe(2)
  })

  it('lists streams without ends instead of dropping them silently', () => {
    const m = buildFlowsheet({ flujos: [flujo(0, v(10)), flujo(1, v(10))], nodos: nodos(1), matriz: [[1, 0]], indices_stock: [] })
    expect(m.sueltos).toEqual([1])
    expect(m.edges).toHaveLength(1)
  })

  it('keys terminals apart from units in the saved layout', () => {
    expect(fs.nodes.map(layoutKey)).toEqual(['N0', 'N1', 'entrada:F0', 'salida:F2', 'stock:F3'])
  })
})

describe('valores', () => {
  const f = flujo(0, v(-100, -104), v(60, 60.6, 2))
  it('shows magnitudes, relative error and tolerance status after a run', () => {
    expect(valores(f, { componente: null, modo: 'ley' }, true)).toMatchObject({ m: 100, r: 104, e: 4, estado: 'limite', usoPct: 80 })
    expect(valores(f, { componente: 0, modo: 'ley' }, true)).toMatchObject({ m: 60, estado: 'ok' })
  })
  it('computes fine tonnage with the worse status of mass and grade', () => {
    const r = valores(f, { componente: 0, modo: 'fino' }, true)
    expect(r.m).toBeCloseTo(60)
    expect(r.r).toBeCloseTo(63.024)
    expect(r.estado).toBe('limite')
  })
  it('has no error nor status before the first run', () => {
    expect(valores(f, { componente: null, modo: 'ley' }, false)).toMatchObject({ e: null, estado: null })
  })
})

describe('desbalances', () => {
  it('is in − out of the shown quantity, in units and % of throughput', () => {
    const flujos = [flujo(0, v(100), v(50)), flujo(1, v(90), v(50))]
    const fs = buildFlowsheet({ flujos, nodos: nodos(1), matriz: [[1, -1]], indices_stock: [] })
    const [d] = desbalances(fs, flujos, { componente: null, modo: 'ley' }, false)
    expect(d!.unidades).toBe(10)
    expect(d!.pct).toBeCloseTo((10 / 95) * 100)
    expect(desbalances(fs, flujos, { componente: 0, modo: 'ley' }, false)[0]!.unidades).toBeCloseTo(5) // fines
  })
})
