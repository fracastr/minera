// Gross-error diagnosis (GET /api/v2/sesiones/{id}/diagnostico): types and the client-side rules around it.
import type { Flujo, Sesion } from './types'

export type VariableDiag = 'tms' | `ley_${number}`
export type Accion = 'corregir_valor' | 'ampliar_tolerancia' | 'revisar_medicion'

export interface Prueba {
  gamma: number | null
  gl: number
  critico: number | null
  pasa: boolean
}

export interface Equivalente {
  flujo: string
  tag: string | null
  variable: VariableDiag
  valor_sugerido: number | null
}

export interface Sospechoso extends Equivalente {
  z: number | null
  valor_medido: number | null
  reduccion_gamma: number | null
  accion: Accion
  equivalentes: Equivalente[]
}

export interface NodoDiag {
  nodo: string | number
  residuo: number | null
  z: number | null
  sospechoso: boolean
}

export interface SerieVar {
  serie: { balance_id: number; periodo: string | null; ajuste_pct: number }[]
  sesgo_recurrente: boolean
  signo: 1 | -1 | null
}

export interface Diagnostico {
  diagnostico: {
    global: Prueba
    global_final: Prueba
    nodos: NodoDiag[]
    sospechosos: Sospechoso[]
    no_redundantes: string[]
    excluidos: string[]
    por_componente: { componente_idx: number; global: Prueba; global_final: Prueba; nodos: NodoDiag[] }[]
  }
  tendencia: Record<string, SerieVar & { leyes: SerieVar[] }>
}

export const ACCION: Record<Accion, string> = {
  corregir_valor: 'Corregir el valor',
  ampliar_tolerancia: 'Ampliar la tolerancia',
  revisar_medicion: 'Revisar la medición en terreno',
}

/** Same defaults as Flask's diagnostico.py. */
export const TOL_DEFECTO_PCT = 5
export const ERROR_GRUESO_PCT = 25
const ALFA = 0.05

/** 'tms' → -1, 'ley_2' → 1 (index into flujo.leyes). */
export const leyIdx = (v: VariableDiag) => (v === 'tms' ? -1 : Number(v.slice(4)) - 1)

export function nombreVariable(v: VariableDiag, componentes: string[]): string {
  const k = leyIdx(v)
  return k < 0 ? 'TMS' : (componentes[k] ?? `Ley ${k + 1}`)
}

export const claveSospechoso = (s: Pick<Equivalente, 'flujo' | 'variable'>) => `${s.flujo}|${s.variable}`

/** Flask names flows by `flujos` (and `tags`); find the session row, preferring an exact tag match. */
export function flujoDe(flujos: Flujo[], nombre: string, tag?: string | null): Flujo | undefined {
  return flujos.find(f => f.nombre === nombre && (tag == null || f.tag === tag)) ?? flujos.find(f => f.nombre === nombre)
}

/**
 * The edit "Aplicar sugerencia" makes. Flask works on |TMS| (a negative TMS only marks a stock's direction),
 * so the sign of the current measurement is kept. Widening sets both tolerances to the correction, rounded up.
 */
export function sugerencia(f: Flujo, v: VariableDiag, accion: Accion, sugerido: number):
  { campo: 'medido'; valor: number } | { campo: 'tolerancia'; valor: number } {
  const x = leyIdx(v) < 0 ? f.tms : f.leyes[leyIdx(v)]!
  if (accion === 'ampliar_tolerancia' && x.medido) {
    const rel = (Math.abs(Math.abs(sugerido) - Math.abs(x.medido)) / Math.abs(x.medido)) * 100
    return { campo: 'tolerancia', valor: Math.max(x.tol_inf, x.tol_sup, Math.ceil(rel * 10) / 10) }
  }
  return { campo: 'medido', valor: leyIdx(v) < 0 && x.medido < 0 ? -Math.abs(sugerido) : sugerido }
}

/** Standard normal quantile (Acklam), as in diagnostico.py. */
export function normPpf(p: number): number {
  const a = [-39.69683028665376, 220.9460984245205, -275.9285104469687, 138.357751867269, -30.66479806614716, 2.506628277459239]
  const b = [-54.47609879822406, 161.5858368580409, -155.6989798598866, 66.80131188771972, -13.28068155288572]
  const c = [-0.007784894002430293, -0.3223964580411365, -2.400758277161838, -2.549732539343734, 4.374664141464968, 2.938163982698783]
  const d = [0.007784695709041462, 0.3224671290700398, 2.445134137142996, 3.754408661907416]
  const pl = 0.02425
  if (p < pl) {
    const q = Math.sqrt(-2 * Math.log(p))
    return (((((c[0]! * q + c[1]!) * q + c[2]!) * q + c[3]!) * q + c[4]!) * q + c[5]!) / ((((d[0]! * q + d[1]!) * q + d[2]!) * q + d[3]!) * q + 1)
  }
  if (p > 1 - pl) return -normPpf(1 - p)
  const q = p - 0.5
  const r = q * q
  return ((((((a[0]! * r + a[1]!) * r + a[2]!) * r + a[3]!) * r + a[4]!) * r + a[5]!) * q) / (((((b[0]! * r + b[1]!) * r + b[2]!) * r + b[3]!) * r + b[4]!) * r + 1)
}

/** Two-sided critical z with Šidák correction over n tests (diagnostico.py's sidak_z). */
export const sidakZ = (n: number) => normPpf(1 - (1 - (1 - ALFA) ** (1 / Math.max(n, 1))) / 2)

export interface ToleranciaAmplia {
  idx: number
  flujo: string
  variable: string
  tolPct: number
}

/**
 * Measurements whose tolerance hides a gross error of ERROR_GRUESO_PCT. With σ = tol·|x|/(100·1,96), that error is
 * z = ERROR_GRUESO_PCT·1,96/tol standard deviations; below the Šidák critical value it cannot be flagged.
 * shortcut: Flask does not expose each measurement's redundancy, which only lowers z further, so this list is a
 * lower bound. Upgrade by returning W_ii/V_ii from /diagnostico.
 */
export function toleranciasAmplias(s: Pick<Sesion, 'flujos' | 'componentes' | 'matriz'>): { tolMax: number; flujos: ToleranciaAmplia[] } {
  const enBalance = (j: number) => s.matriz.some(row => (row[j] ?? 0) !== 0)
  const medidos = s.flujos.filter(f => f.tms.medido !== 0 && enBalance(f.idx))
  const tolMax = (ERROR_GRUESO_PCT * 1.96) / sidakZ(medidos.length)
  const flujos = medidos.flatMap(f =>
    [{ variable: 'TMS', v: f.tms }, ...f.leyes.map((v, k) => ({ variable: s.componentes[k] ?? `Ley ${k + 1}`, v }))].flatMap(({ variable, v }) => {
      const tol = (v.tol_inf + v.tol_sup) / 2
      const tolPct = tol > 0 ? tol : TOL_DEFECTO_PCT
      return v.medido && tolPct > tolMax ? [{ idx: f.idx, flujo: f.nombre, variable, tolPct }] : []
    }),
  )
  return { tolMax, flujos: flujos.sort((x, y) => y.tolPct - x.tolPct) }
}

/** Node throughput (inputs + outputs) / 2 from measured TMS, to express a residual as %. */
export function caudalNodo(s: Pick<Sesion, 'flujos' | 'matriz' | 'nodos'>, nombre: string | number): number | null {
  const n = typeof nombre === 'number' ? s.nodos[nombre] : s.nodos.find(x => x.nombre === nombre)
  const row = n ? s.matriz[n.idx] : undefined
  if (!row) return null
  const t = row.reduce((sum, c, j) => sum + Math.abs(c) * Math.abs(s.flujos[j]?.tms.medido ?? 0), 0) / 2
  return t > 0 ? t : null
}
