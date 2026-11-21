// Balance assistant (POST /api/v2/sesiones/{id}/asistente): types, the safe text formatter and proposal → edit.
import { flujoDe, leyIdx, sugerencia, type Accion, type VariableDiag } from './diagnostico'
import type { Sesion } from './types'

export interface Propuesta {
  flujo: string
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

/**
 * The grid edit a proposal makes through store.aplicarSugerencia(), or null when there is nothing to apply
 * (a field check, an unknown flow, a component the session does not have). `antes`/`despues` preview the change.
 */
export function edicionDePropuesta(p: Propuesta, s: Pick<Sesion, 'flujos' | 'componentes'>):
  | { flujo: number; variable: VariableDiag; accion: Accion; sugerido: number; campo: 'medido' | 'tolerancia'; antes: number; despues: number }
  | null {
  const f = flujoDe(s.flujos, p.flujo)
  const k = leyIdx(p.variable)
  const x = !f ? undefined : k < 0 ? f.tms : f.leyes[k]
  if (!f || !x || p.tipo === 'revisar_medicion' || p.valor === null || !Number.isFinite(p.valor) || p.valor <= 0) return null
  const sg = sugerencia(f, p.variable, p.tipo, p.valor)
  return {
    flujo: f.idx, variable: p.variable, accion: p.tipo, sugerido: p.valor, campo: sg.campo,
    antes: sg.campo === 'medido' ? x.medido : Math.max(x.tol_inf, x.tol_sup), despues: sg.valor,
  }
}
