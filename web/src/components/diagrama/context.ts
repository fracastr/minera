import type { InjectionKey } from 'vue'
import type { Estado } from '@/domain/reconciliationQuality'
import type { Pt, Rect } from './layout'

export type Tono = 'normal' | 'limite' | 'fuera'
export const tono = (e: Estado | null): Tono => (e === 'fuera' ? 'fuera' : e === 'limite' ? 'limite' : 'normal')

export interface Chip {
  nombre: string
  /** Label parts in order, e.g. [['M', '17.900'], ['R', '17.890'], ['E', '+0,06 %']]. */
  partes: [string, string][]
  tono: Tono
  /** Width of the chip for the current toggles (the layout reserves the widest). */
  ancho: number
  aria: string
}

export interface NodoVista {
  label: string
  delta: string
  deltaTono: 'normal' | 'fuera'
  aria: string
}

/** What the custom node and edge components read: the parent owns it and recomputes it reactively. */
export interface DiagramCtx {
  geom: Record<string, { points: Pt[]; label: Rect | null }>
  chips: Chip[]
  nodos: Record<string, NodoVista>
  /** Edge id → 'in' | 'out' relative to the selected node, or 'dim'; absent = no node selected. */
  foco: Record<string, 'in' | 'out' | 'dim'>
  nodosAtenuados: Set<string>
  /** Diagnosis overlay: flows with a suspect measurement; null = overlay off. */
  sospechosos: Set<number> | null
  /** Flow pulsing after "Ver en diagrama". */
  pulso: number | null
  flujoSel: number | null
  flujoHover: number | null
  abrirFlujo: (j: number) => void
  hover: (j: number | null, e?: PointerEvent) => void
  elegirNodo: (id: string) => void
}

export const DIAGRAM_CTX: InjectionKey<DiagramCtx> = Symbol('diagrama')
