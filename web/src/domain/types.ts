/** Shapes of the /api/v2 balance endpoints. Numbers keep full precision; rounding is for display only. */

export interface Variable {
  medido: number
  reconciliado: number
  /** Tolerances in %, as the solver reads them. */
  tol_inf: number
  tol_sup: number
  /** % the reconciled value moved below (inf) or above (sup) the measurement in the last run. */
  desv_inf: number
  desv_sup: number
}

export interface Flujo {
  idx: number
  nombre: string
  tag: string | null
  descripcion: string | null
  jerarquia: number
  /** Stock delta (indices_stock); its TMS sign says which way the stock moved. */
  stock: boolean
  tms: Variable
  leyes: Variable[]
}

export interface Nodo {
  idx: number
  nombre: string
  residuo_tms: number
  residuo_finos: number[]
}

export interface Inventario {
  idx: number
  nombre: string
  tmh_ini: number
  tmh_fin: number
  tmh_delta: number
  humedad: number
  tms_ini: number
  tms_fin: number
  tms_delta: number
  leyes: number[]
}

export interface BalanceRef {
  id: number
  nombre: string
  tipo: 'quincenal' | 'mensual' | null
  periodo_inicio: string | null
  periodo_fin: string | null
}

export interface Sesion {
  id: number
  balance: BalanceRef | null
  proceso: { id: number; nombre: string; valle: { id: number; nombre: string } }
  componentes: string[]
  corrida: boolean
  /** GEKKO status of the last solver call; ok=false: it did not converge and the reconciled values are not trustworthy. null = unknown. */
  solver: { ok: boolean; mensaje: string | null; iteraciones: number | null } | null
  flujos: Flujo[]
  nodos: Nodo[]
  /** nodes × flows: +1 the flow enters the node, −1 it leaves. */
  matriz: number[][]
  indices_stock: number[][]
  inventarios: Inventario[]
  updated_at: string | null
}

export interface Edits {
  flujos: {
    idx: number
    tms_medido: number
    leyes_medido: number[]
    tms_tol_inf: number
    tms_tol_sup: number
    leyes_tol: [number, number][]
    jerarquia: number
  }[]
  inventarios: { idx: number; humedad: number; tms_ini: number; tms_fin: number; leyes: number[] }[]
}

export interface Corrida {
  id: number
  tipo: 'importacion' | 'corrida' | 'restauracion'
  usuario: { id: number; nombre: string } | null
  created_at: string
  actual: boolean
}

export interface Valle {
  id: number
  nombre: string
  procesos: { id: number; nombre: string; componentes: string[] }[]
}

export interface BalanceRow {
  id: number
  nombre: string
  tipo: 'quincenal' | 'mensual' | null
  periodo_inicio: string | null
  periodo_fin: string | null
  proceso: { id: number; nombre: string } | null
  valle: { id: number; nombre: string } | null
  usuario: { id: number; nombre: string } | null
  created_at: string
  datos_entrada_id: number | null
}

/** Everything the user can edit, sent with Correr and before Guardar. */
export function toEdits(s: Sesion): Edits {
  return {
    flujos: s.flujos.map(f => ({
      idx: f.idx,
      tms_medido: f.tms.medido,
      leyes_medido: f.leyes.map(l => l.medido),
      tms_tol_inf: f.tms.tol_inf,
      tms_tol_sup: f.tms.tol_sup,
      leyes_tol: f.leyes.map(l => [l.tol_inf, l.tol_sup] as [number, number]),
      jerarquia: f.jerarquia,
    })),
    inventarios: s.inventarios.map(i => ({ idx: i.idx, humedad: i.humedad, tms_ini: i.tms_ini, tms_fin: i.tms_fin, leyes: i.leyes })),
  }
}

/** Same rule the server applies on save: TMS delta = fin − ini, TMH from moisture. */
export function recomputeInventario(i: Inventario): void {
  const tmh = (tms: number) => tms / (1 - i.humedad / 100)
  // Rounded so fin == ini gives exactly 0, not float noise (same as SesionMapper::applyEdits).
  const r10 = (n: number) => Math.round(n * 1e10) / 1e10
  i.tms_delta = r10(i.tms_fin - i.tms_ini)
  i.tmh_ini = tmh(i.tms_ini)
  i.tmh_fin = tmh(i.tms_fin)
  i.tmh_delta = r10(tmh(i.tms_fin - i.tms_ini))
}
