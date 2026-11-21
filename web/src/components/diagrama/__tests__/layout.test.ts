// @vitest-environment node
import ELK from 'elkjs/lib/elk.bundled.js'
import { describe, expect, it } from 'vitest'
import { buildFlowsheet } from '@/domain/flowsheet'
import type { Flujo } from '@/domain/types'
import { labelOn, layoutFlowsheet, posiciones, routeOrthogonal, type Layout } from '../layout'

const v = (x: number) => ({ medido: x, reconciliado: x, tol_inf: 5, tol_sup: 5, desv_inf: 0, desv_sup: 0 })
const flujo = (idx: number): Flujo => ({ idx, nombre: `F${idx}`, tag: null, descripcion: null, jerarquia: 1, stock: false, tms: v(10), leyes: [] })

describe('layoutFlowsheet', () => {
  it('lays out left to right with orthogonal routes and a label box per stream', async () => {
    // feed → N0 → N1 → product, plus a recycle N1 → N0.
    const fs = buildFlowsheet({
      flujos: [0, 1, 2, 3].map(flujo),
      nodos: [0, 1].map(idx => ({ idx, nombre: `N${idx}`, residuo_tms: 0, residuo_finos: [] })),
      matriz: [[1, -1, 0, 1], [0, 1, -1, -1]],
      indices_stock: [],
    })
    const l = await layoutFlowsheet(new ELK(), fs, n => (n.tipo === 'unidad' ? { width: 120, height: 48 } : { width: 22, height: 22 }), () => ({ width: 90, height: 30 }))
    expect(l.nodes['entrada:0']!.x).toBeLessThan(l.nodes['n:0']!.x)
    expect(l.nodes['n:0']!.x).toBeLessThan(l.nodes['n:1']!.x)
    expect(l.nodes['salida:2']!.x).toBeGreaterThan(l.nodes['n:1']!.x)
    for (const e of fs.edges) {
      const { points, label } = l.edges[e.id]!
      expect(points.length).toBeGreaterThanOrEqual(2)
      points.slice(1).forEach((p, i) => expect(p.x === points[i]!.x || p.y === points[i]!.y).toBe(true))
      expect(label).toMatchObject({ width: 90, height: 30 })
      expect(label!.x).toBeGreaterThan(0)
    }
  })
})

describe('routeOrthogonal', () => {
  it('steps forward through the middle and loops backwards edges below', () => {
    expect(routeOrthogonal({ x: 0, y: 0 }, { x: 100, y: 40 }, 0)).toEqual([{ x: 0, y: 0 }, { x: 50, y: 0 }, { x: 50, y: 40 }, { x: 100, y: 40 }])
    const back = routeOrthogonal({ x: 100, y: 0 }, { x: 0, y: 0 }, 60)
    expect(back.every(p => p.y <= 84)).toBe(true)
    expect(back[2]!.y).toBe(84)
    expect(labelOn(back, { width: 20, height: 10 })).toMatchObject({ y: 79 })
  })
})

describe('posiciones', () => {
  const l: Layout = {
    nodes: { a: { x: 0, y: 0, width: 100, height: 40 }, b: { x: 200, y: 0, width: 100, height: 40 }, c: { x: 200, y: 80, width: 100, height: 40 } },
    edges: {},
  }
  it('keeps unsaved drags, then saved positions, then ELK', () => {
    expect(posiciones(['a', 'b', 'c'], l, {}, () => undefined)).toEqual({ a: { x: 0, y: 0 }, b: { x: 200, y: 0 }, c: { x: 200, y: 80 } })
    const p = posiciones(['a', 'b', 'c', 'gone'], l, { a: { x: 5, y: 5 } }, id => ({ x: 1, y: 2 }))
    expect(p).toEqual({ a: { x: 5, y: 5 }, b: { x: 1, y: 2 }, c: { x: 1, y: 2 } })
  })
  it('puts nodes new to a saved layout below it without overlapping', () => {
    const p = posiciones(['a', 'b', 'c'], l, {}, id => (id === 'a' ? { x: 0, y: 300 } : undefined))
    expect(p.a).toEqual({ x: 0, y: 300 })
    expect(p.b!.y).toBe(300 + 40 + 48)
    expect(p.c!.y - p.b!.y).toBe(80) // ELK's spacing among the new ones is kept
  })
})
