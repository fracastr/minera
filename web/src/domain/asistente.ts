// Balance assistant (POST /api/v2/sesiones/{id}/asistente): types, the safe text formatter and proposal → edit.
import { fmt } from '@/lib/format'
import { flujoDe, leyIdx, sugerencia, toleranciasAmplias, type Accion, type Sospechoso, type VariableDiag } from './diagnostico'
import type { Flujo, Sesion } from './types'

export interface Propuesta {
  flujo: string
  /** Session row (the server resolves it; names can repeat). */
  idx?: number
  variable: 'tms' | 'ley_1' | 'ley_2'
  tipo: Accion
  valor: number | null
  motivo: string
  /** Client only: the user applied it in this visit. */
  aplicada?: boolean
}

export interface RespuestaAsistente {
  texto: string
  propuestas: Propuesta[]
  uso: { input_tokens: number; output_tokens: number }
}

export interface MensajeChat {
  rol: 'usuario' | 'asistente'
  texto: string
  propuestas?: Propuesta[]
}

export type Bloque = { tipo: 'p'; texto: string } | { tipo: 'ul' | 'ol'; items: string[] }

/**
 * Model text → paragraphs and lists, rendered by the template as text nodes (never HTML), so whatever the model
 * writes cannot inject markup. Markdown emphasis markers are dropped rather than interpreted.
 */
export function bloques(texto: string): Bloque[] {
  const out: Bloque[] = []
  const limpia = (s: string) => s.replace(/\*\*|__|`/g, '').replace(/^#{1,6}\s+/, '').trim()
  // A blank line closes the current block; inside a block, list lines group into a list and other lines join one paragraph.
  for (const parrafo of texto.replace(/\r\n?/g, '\n').split(/\n\s*\n/)) {
    let actual: Bloque | null = null
    for (const linea of parrafo.split('\n')) {
      const t = linea.trim()
      if (!t) continue
      const m = /^(?:([-*•])|(\d+)[.)])\s+(.*)$/.exec(t)
      const tipo = m ? (m[1] ? 'ul' : 'ol') : 'p'
      const contenido = limpia(m ? m[3]! : t)
      if (!contenido) continue
      if (actual?.tipo === 'p' && tipo === 'p') actual.texto += ` ${contenido}`
      else if (actual && actual.tipo !== 'p' && actual.tipo === tipo) actual.items.push(contenido)
      else out.push((actual = tipo === 'p' ? { tipo, texto: contenido } : { tipo, items: [contenido] }))
    }
  }
  return out
}

/** Same bounds as Asistente::validarPropuesta() in Laravel. */
export const VECES_TOL = 3
export const SIN_TOL_PCT = 50

export type Edicion = { flujo: number; variable: VariableDiag; accion: Accion; sugerido: number; campo: 'medido' | 'tolerancia'; antes: number; despues: number }

/** The session row a proposal names: by idx when the server sent it (names can repeat), else by unique name. */
export function flujoDePropuesta(p: Propuesta, flujos: Flujo[]): Flujo | undefined {
  if (p.idx != null) return flujos[p.idx]
  const m = flujos.filter(f => f.nombre === p.flujo)
  return m.length === 1 ? m[0] : undefined
}

/**
 * The grid edit a proposal makes through store.aplicarSugerencia(); null when there is nothing to apply (a field
 * check, an unknown flow, a component the session does not have); `{ error }` when it breaks the server's bounds:
 * a correction beyond VECES_TOL × the tolerance (SIN_TOL_PCT % without one) unless it is the diagnosis's own
 * suggestion, or a widened tolerance above toleranciasAmplias().tolMax. `antes`/`despues` preview the change.
 */
export function edicionDePropuesta(
  p: Propuesta,
  s: Pick<Sesion, 'flujos' | 'componentes' | 'matriz'>,
  sospechosos: Sospechoso[] = [],
): Edicion | { error: string } | null {
  const f = flujoDePropuesta(p, s.flujos)
  const k = leyIdx(p.variable)
  const x = !f ? undefined : k < 0 ? f.tms : f.leyes[k]
  if (!f || !x || p.tipo === 'revisar_medicion' || p.valor === null || !Number.isFinite(p.valor) || p.valor <= 0) return null
  const valor = p.valor
  const med = Math.abs(x.medido)
  if (!med) return { error: 'La medición está en cero; revísala en terreno.' }
  const cambio = (Math.abs(p.valor - med) / med) * 100
  if (p.tipo === 'corregir_valor') {
    const tol = Math.max(x.tol_inf, x.tol_sup)
    const limite = tol > 0 ? VECES_TOL * tol : SIN_TOL_PCT
    const esSugerido = sospechosos.flatMap(sp => [sp, ...sp.equivalentes])
      .some(e => e.variable === p.variable && e.valor_sugerido !== null && flujoDe(s.flujos, e.flujo, e.tag)?.idx === f.idx
        && Math.abs(Math.abs(e.valor_sugerido) - valor) <= 0.005 * Math.abs(e.valor_sugerido))
    if (cambio > limite && !esSugerido) return { error: `Cambia la medición ${fmt(cambio, 1)} %, más que el máximo de ${fmt(limite, 1)} %.` }
  }
  const sg = sugerencia(f, p.variable, p.tipo, p.valor)
  if (sg.campo === 'tolerancia') {
    const { tolMax } = toleranciasAmplias(s)
    if (sg.valor > tolMax) return { error: `La tolerancia quedaría en ${fmt(sg.valor, 1)} %, sobre ${fmt(tolMax, 1)} %: el diagnóstico ya no detectaría errores en esta medición.` }
  }
  return {
    flujo: f.idx, variable: p.variable, accion: p.tipo, sugerido: p.valor, campo: sg.campo,
    antes: sg.campo === 'medido' ? x.medido : Math.max(x.tol_inf, x.tol_sup), despues: sg.valor,
  }
}
