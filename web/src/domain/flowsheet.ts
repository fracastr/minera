// Flowsheet model for the Diagrama tab: process units, boundary terminals and one edge per stream end pair.
// matriz is nodes × flows: +1 = the flow enters the node, −1 = it leaves (see flowGraph.ts / SesionMapper).
import { ajuste, type Estado } from './reconciliationQuality'
import type { Flujo, Sesion, Variable } from './types'

export type TipoNodo = 'unidad' | 'entrada' | 'salida' | 'stock'

export interface FsNode {
  id: string
  tipo: TipoNodo
  label: string
  /** Matrix row of a process unit. */
  nodo?: number
  /** Stream that owns a terminal (entrada / salida / stock). */
  flujo?: number
}

export interface FsEdge {
  id: string
  flujo: number
  source: string
  target: string
  stock: boolean
  /** One edge per stream carries its label; the others of a multi-end stream share it. */
  primary: boolean
}

export interface Flowsheet {
  nodes: FsNode[]
  edges: FsEdge[]
  /** Matrix with each stock coefficient resolved (the stored one is 0 when the delta was 0). */
  coef: number[][]
  /** Streams with no end in the matrix nor in indices_stock: listed, never drawn. */
  sueltos: number[]
}

type Topologia = Pick<Sesion, 'flujos' | 'nodos' | 'matriz' | 'indices_stock'>

export const unitId = (n: number) => `n:${n}`

export function buildFlowsheet(s: Topologia): Flowsheet {
  const coef = s.nodos.map((_, i) => s.flujos.map((_, j) => s.matriz[i]?.[j] ?? 0))
  // indices_stock = [[node+1, flow+1]]. The solver sets that coefficient to −sign(TMS): a drawn-down
  // stock (negative delta) enters the node. With a zero delta we still draw it, as accumulation.
  for (const [n1, f1] of s.indices_stock) {
    const i = (n1 ?? 0) - 1
    const j = (f1 ?? 0) - 1
    const f = s.flujos[j]
    if (!f || !coef[i] || coef[i]![j]) continue
    coef[i]![j] = f.tms.medido < 0 ? 1 : -1
  }

  const nodes: FsNode[] = s.nodos.map(n => ({ id: unitId(n.idx), tipo: 'unidad', label: n.nombre || `Nodo ${n.idx + 1}`, nodo: n.idx }))
  const edges: FsEdge[] = []
  const sueltos: number[] = []

  s.flujos.forEach((f, j) => {
    const into = coef.flatMap((row, i) => (row[j]! > 0 ? [unitId(i)] : []))
    const out = coef.flatMap((row, i) => (row[j]! < 0 ? [unitId(i)] : []))
    if (!into.length && !out.length) {
      sueltos.push(j)
      return
    }
    // The missing end is a terminal: the inventory for stock deltas, else the system boundary.
    const terminal = (tipo: TipoNodo) => {
      const id = `${tipo}:${j}`
      nodes.push({ id, tipo, label: f.nombre, flujo: j })
      return [id]
    }
    const sources = out.length ? out : terminal(f.stock ? 'stock' : 'entrada')
    const targets = into.length ? into : terminal(f.stock ? 'stock' : 'salida')
    // More than two ends: one edge from each source to each destination, sharing the stream's label.
    for (const source of sources)
      for (const target of targets)
        edges.push({ id: `f${j}:${edges.filter(e => e.flujo === j).length}`, flujo: j, source, target, stock: f.stock, primary: !edges.some(e => e.flujo === j) })
  })

  return { nodes, edges, coef, sueltos }
}

/** What the diagram shows on each stream: mass, or one component's grade or fine tonnage. */
export interface Vista {
  /** null = Masa (TMS); k = component k. */
  componente: number | null
  modo: 'ley' | 'fino'
}

export interface Valores {
  m: number
  r: number
  /** Relative error (R − M) / M in %, null without a measurement. */
  e: number | null
  /** Tolerance status, null before the first run or without a bounded tolerance. */
  estado: Estado | null
  usoPct: number | null
  tolInf: number | null
  tolSup: number | null
}

const fino = (tms: number, ley: number) => (Math.abs(tms) * ley) / 100
const ORDEN: Estado[] = ['sin-tolerancia', 'ok', 'limite', 'fuera']

function deVariable(v: Variable, jerarquia: number, corrida: boolean): Valores {
  const a = ajuste(v, jerarquia)
  return {
    m: Math.abs(v.medido),
    r: Math.abs(v.reconciliado),
    e: corrida && a ? a.ajustePct : null,
    estado: corrida && a ? a.estado : null,
    usoPct: corrida && a ? a.usoPct : null,
    tolInf: v.tol_inf,
    tolSup: v.tol_sup,
  }
}

export function valores(f: Flujo, vista: Vista, corrida: boolean): Valores {
  if (vista.componente === null) return deVariable(f.tms, f.jerarquia, corrida)
  const ley = f.leyes[vista.componente]
  if (!ley) return { m: 0, r: 0, e: null, estado: null, usoPct: null, tolInf: null, tolSup: null }
  if (vista.modo === 'ley') return deVariable(ley, f.jerarquia, corrida)
  // Fine tonnage has no tolerance of its own: it takes the worse of its mass and its grade.
  const t = deVariable(f.tms, f.jerarquia, corrida)
  const l = deVariable(ley, f.jerarquia, corrida)
  const m = fino(f.tms.medido, ley.medido)
  const r = fino(f.tms.reconciliado, ley.reconciliado)
  const peor = [t, l].reduce((a, b) => (ORDEN.indexOf(b.estado ?? 'sin-tolerancia') > ORDEN.indexOf(a.estado ?? 'sin-tolerancia') ? b : a))
  return { m, r, e: corrida && m ? ((r - m) / m) * 100 : null, estado: peor.estado, usoPct: peor.usoPct, tolInf: null, tolSup: null }
}

export interface Desbalance {
  unidades: number
  /** Residual over throughput ((in + out) / 2), null when nothing flows. */
  pct: number | null
}

/**
 * Node imbalance (in − out) of the shown quantity: mass for Masa, fine tonnage for a component
 * (grades do not balance, their fines do). Measured values before the first run, reconciled after.
 */
export function desbalances(fs: Flowsheet, flujos: Flujo[], vista: Vista, reconciliado: boolean): Desbalance[] {
  const x = flujos.map(f => {
    const tms = reconciliado ? f.tms.reconciliado : f.tms.medido
    if (vista.componente === null) return Math.abs(tms)
    const ley = f.leyes[vista.componente]
    return ley ? fino(tms, reconciliado ? ley.reconciliado : ley.medido) : 0
  })
  return fs.coef.map(row => {
    let entra = 0
    let sale = 0
    row.forEach((c, j) => {
      if (c > 0) entra += x[j]!
      else if (c < 0) sale += x[j]!
    })
    const unidades = entra - sale
    const paso = (entra + sale) / 2
    return { unidades, pct: paso > 0 ? (unidades / paso) * 100 : null }
  })
}

/** Key of a node in the saved layout: the unit name, or "<tipo>:<stream name>" for terminals. */
export const layoutKey = (n: FsNode) => (n.tipo === 'unidad' ? n.label : `${n.tipo}:${n.label}`)
