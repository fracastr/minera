import { describe, expect, it } from 'vitest'
import { buildFlowGraph, nodeStreams } from '../flowGraph'
import { sugerirFin } from '../periodo'
import { ajuste, computeQuality, estadoDeUso } from '../reconciliationQuality'
import { recomputeInventario, toEdits, type Flujo, type Inventario, type Variable } from '../types'

const v = (medido: number, reconciliado: number, tol = 5): Variable => ({ medido, reconciliado, tol_inf: tol, tol_sup: tol, desv_inf: 0, desv_sup: 0 })
const flujo = (idx: number, nombre: string, tms: Variable, ley: Variable, extra: Partial<Flujo> = {}): Flujo => ({
  idx, nombre, tag: null, descripcion: null, jerarquia: 1, stock: false, tms, leyes: [ley], ...extra,
})

// Mina → Planta → (Concentrado out, Stock delta out); Planta is node 0.
const flujos = [
  flujo(0, 'Mina', v(100, 104), v(60, 60.5)),
  flujo(1, 'Concentrado', v(80, 80.9), v(65, 65)),
  flujo(2, 'Delta stock', v(-25, -23.1, 10), v(50, 50), { stock: true, jerarquia: 2 }),
]
const matriz = [[1, -1, -1]]
const nodos = [{ idx: 0, nombre: 'Planta', residuo_tms: 0.0004, residuo_finos: [0] }]

describe('reconciliationQuality', () => {
  it('classifies tolerance use', () => {
    expect(estadoDeUso(null)).toBe('sin-tolerancia')
    expect(estadoDeUso(50)).toBe('ok')
    expect(estadoDeUso(80)).toBe('limite')
    expect(estadoDeUso(100.4)).toBe('limite')
    expect(estadoDeUso(101)).toBe('fuera')
  })

  it('measures adjustments on magnitudes so a negative stock is not inverted', () => {
    expect(ajuste(v(100, 104), 1)).toMatchObject({ ajustePct: 4, usoPct: 80, estado: 'limite' })
    expect(ajuste(v(-100, -106), 1)).toMatchObject({ estado: 'fuera' })
    expect(ajuste(v(-100, -106), 2)).toMatchObject({ usoPct: null, estado: 'sin-tolerancia' })
    expect(ajuste(v(0, 3), 1)).toBeNull()
  })

  it('summarizes a session', () => {
    const q = computeQuality({ flujos, nodos, matriz, componentes: ['FeT'] })
    expect(q.summary.enLimite).toBe(1) // Mina TMS at 80 %
    expect(q.summary.fuera).toBe(0)
    expect(q.summary.mayorAjusteTms?.flujo).toBe('Delta stock')
    expect(q.flows.find(f => f.flujo === 'Mina' && f.variable === 'FeT')?.usoPct).toBeCloseTo(16.67, 1)
    expect(q.nodes[0]).toMatchObject({ nodo: 'Planta', cierra: true })
    expect(q.summary.nodosSinCerrar).toBe(0)
  })

  it('flags a node whose residual exceeds 0.1 % of its throughput', () => {
    const q = computeQuality({ flujos, nodos: [{ ...nodos[0]!, residuo_tms: 5 }], matriz, componentes: ['FeT'] })
    expect(q.nodes[0]!.cierra).toBe(false)
  })
})

describe('flowGraph', () => {
  it('lists the streams entering and leaving a node', () => {
    expect(nodeStreams(matriz, 0)).toEqual({ entran: [0], salen: [1, 2] })
    expect(nodeStreams(matriz, 3)).toEqual({ entran: [], salen: [] })
  })

  it('builds edges with external ends and types', () => {
    const g = buildFlowGraph({ matriz, nodos: ['Planta'], flujos })
    expect(g.links.map(l => [l.source, l.target, l.tipo])).toEqual([
      ['Mina', 'Planta', 'entrada'],
      ['Planta', 'Concentrado', 'salida'],
      ['Planta', 'Delta stock', 'stock'],
    ])
    expect(g.totales).toMatchObject({ entradas: 104, salidas: 80.9, stockAcumulacion: 23.1 })
  })

  it('splits recirculations out of the DAG', () => {
    const g = buildFlowGraph({
      matriz: [[1, -1, 1], [0, 1, -1]],
      nodos: ['A', 'B'],
      flujos: [flujo(0, 'In', v(1, 1), v(1, 1)), flujo(1, 'AB', v(1, 1), v(1, 1)), flujo(2, 'BA', v(1, 1), v(1, 1))],
    })
    expect(g.recirculaciones.map(e => e.flujo)).toEqual(['BA'])
  })
})

describe('periodo', () => {
  it('suggests v1 period ends', () => {
    expect(sugerirFin('2026-11-01', 'quincenal')).toBe('2026-11-15')
    expect(sugerirFin('2026-11-16', 'quincenal')).toBe('2026-11-30')
    expect(sugerirFin('2028-02-03', 'mensual')).toBe('2028-02-29')
    expect(sugerirFin('', 'mensual')).toBe('')
  })
})

describe('edits', () => {
  it('recomputes inventory deltas from TMS and moisture', () => {
    const i: Inventario = { idx: 0, nombre: 'Pila', tmh_ini: 0, tmh_fin: 0, tmh_delta: 0, humedad: 20, tms_ini: 80, tms_fin: 160, tms_delta: 0, leyes: [50] }
    recomputeInventario(i)
    expect(i).toMatchObject({ tms_delta: 80, tmh_ini: 100, tmh_fin: 200, tmh_delta: 100 })
  })

  it('gives exactly zero deltas for an unchanged inventory at 7,3 % moisture', () => {
    const i = { idx: 0, nombre: 'Pila', tmh_ini: 0, tmh_fin: 0, tmh_delta: 1, humedad: 7.3, tms_ini: 12345.678, tms_fin: 12345.678, tms_delta: 1, leyes: [55] } as Inventario
    recomputeInventario(i)
    expect([i.tms_delta, i.tmh_delta]).toEqual([0, 0])
    expect(Object.is(i.tmh_delta, -0)).toBe(false)
  })

  it('sends every editable value at full precision', () => {
    const s = { flujos: [flujo(0, 'Mina', v(100.123456, 104), v(60.987654, 60.5))], inventarios: [] } as never
    expect(toEdits(s).flujos[0]).toEqual({
      idx: 0, tms_medido: 100.123456, leyes_medido: [60.987654], tms_tol_inf: 5, tms_tol_sup: 5, leyes_tol: [[5, 5]], jerarquia: 1,
    })
  })
})
