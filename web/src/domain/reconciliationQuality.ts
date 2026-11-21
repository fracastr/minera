// Reconciliation quality from a session (port of the v1/v3 reconciliationQuality.js to the v2 DTO).
// Mirrors the solver: flows with jerarquía 1 are bounded by their tolerances; others use limits the UI does not show.
import type { Flujo, Nodo, Variable } from './types'

export const NEAR_LIMIT_PCT = 80
export const NODE_CLOSURE_TOLERANCE_PCT = 0.1

export type Estado = 'ok' | 'limite' | 'fuera' | 'sin-tolerancia'

export interface Ajuste {
  flujo: string
  idx: number
  /** 'TMS' or a component name (FeT, FeMag). */
  variable: string
  medido: number
  reconciliado: number
  ajustePct: number
  usoPct: number | null
  estado: Estado
}

export function estadoDeUso(usoPct: number | null): Estado {
  if (usoPct === null) return 'sin-tolerancia'
  if (usoPct > 100.5) return 'fuera'
  if (usoPct >= NEAR_LIMIT_PCT) return 'limite'
  return 'ok'
}

/**
 * How far the run moved one variable and how much of its tolerance that used.
 * Magnitudes, not signed values: the solver bounds |x| and a negative TMS only marks a stock's direction.
 */
export function ajuste(v: Variable, jerarquia: number): { ajustePct: number; usoPct: number | null; estado: Estado } | null {
  const med = Math.abs(v.medido)
  if (!med) return null
  const ajustePct = ((Math.abs(v.reconciliado) - med) / med) * 100
  const tol = ajustePct >= 0 ? v.tol_sup : v.tol_inf
  const usoPct = jerarquia === 1 && tol > 0 ? (Math.abs(ajustePct) / tol) * 100 : null
  return { ajustePct, usoPct, estado: estadoDeUso(usoPct) }
}

export function flowAdjustments(flujos: Flujo[], componentes: string[]): Ajuste[] {
  return flujos.flatMap(f =>
    [{ variable: 'TMS', v: f.tms }, ...f.leyes.map((v, k) => ({ variable: componentes[k] ?? `Ley ${k + 1}`, v }))].flatMap(({ variable, v }) => {
      const a = ajuste(v, f.jerarquia)
      return a ? [{ flujo: f.nombre, idx: f.idx, variable, medido: v.medido, reconciliado: v.reconciliado, ...a }] : []
    }),
  )
}

export interface Cierre {
  nodo: string
  idx: number
  residual: number
  cierrePct: number | null
  cierra: boolean
}

// Node throughput = (inputs + outputs) / 2, independent of the matrix sign convention.
export function nodeClosures(nodos: Nodo[], matriz: number[][], flujos: Flujo[]): Cierre[] {
  return nodos.map(n => {
    const coefs = matriz[n.idx] ?? []
    const throughput = coefs.reduce((sum, c, j) => sum + Math.abs(c) * Math.abs(flujos[j]?.tms.reconciliado ?? 0), 0) / 2
    const residual = n.residuo_tms
    const cierrePct = throughput > 0 ? (Math.abs(residual) / throughput) * 100 : null
    return {
      nodo: n.nombre,
      idx: n.idx,
      residual,
      cierrePct,
      cierra: cierrePct === null ? Math.abs(residual) < 1 : cierrePct <= NODE_CLOSURE_TOLERANCE_PCT,
    }
  })
}

export function computeQuality(s: { flujos: Flujo[]; nodos: Nodo[]; matriz: number[][]; componentes: string[] }) {
  const flows = flowAdjustments(s.flujos, s.componentes)
  const nodes = nodeClosures(s.nodos, s.matriz, s.flujos)
  const mayorAjusteTms = flows
    .filter(f => f.variable === 'TMS')
    .reduce<Ajuste | null>((max, f) => (!max || Math.abs(f.ajustePct) > Math.abs(max.ajustePct) ? f : max), null)

  return {
    flows,
    nodes,
    summary: {
      totalFlujos: s.flujos.length,
      enLimite: flows.filter(f => f.estado === 'limite').length,
      fuera: flows.filter(f => f.estado === 'fuera').length,
      mayorAjusteTms,
      nodosSinCerrar: nodes.filter(n => !n.cierra).length,
      totalNodos: nodes.length,
    },
  }
}
