<script setup lang="ts">
import '@vue-flow/core/dist/style.css'
import { FileImage, FileCode2, LoaderCircle, Maximize, Minimize, Minus, Plus, RotateCcw, Save, Scan } from '@lucide/vue'
import { useVueFlow, VueFlow, type Node, type NodeDragEvent } from '@vue-flow/core'
import { computed, nextTick, onBeforeUnmount, onMounted, provide, reactive, ref, shallowRef, watch } from 'vue'
import Notice from '@/components/Notice.vue'
import { buildFlowsheet, desbalances, layoutKey, layoutKeys, unitId, valores, type Flowsheet, type FsEdge, type FsNode, type Vista } from '@/domain/flowsheet'
import { NODE_CLOSURE_TOLERANCE_PCT } from '@/domain/reconciliationQuality'
import type { Sesion } from '@/domain/types'
import { api, errorMessage } from '@/lib/api'
import { notify } from '@/lib/feedback'
import { hasta } from '@/lib/hasta'
import { fmt } from '@/lib/format'
import { useDiagramImage } from '@/composables/useDiagramImage'
import { useSesionStore } from '@/stores/sesion'
import { DIAGRAM_CTX, tono, type Chip, type DiagramCtx, type NodoVista } from './context'
import { acquireElk, dropElk, getElk, releaseElk } from './elk'
import FlowNode from './FlowNode.vue'
import FlujoPanel from './FlujoPanel.vue'
import { boundsOf, fitZoom, labelOn, layoutFlowsheet, posiciones, routeOrthogonal, TERMINAL, type Layout, type Pt, type Size } from './layout'
import StreamEdge from './StreamEdge.vue'

const props = defineProps<{ sesion: Sesion; editable: boolean }>()
const emit = defineEmits<{ editar: [flujo: number] }>()
const store = useSesionStore()
const img = useDiagramImage()

const flowId = `diagrama-${Math.random().toString(36).slice(2)}`
const { fitView, zoomIn, zoomOut, zoomTo, panBy, setViewport, findNode, onNodesInitialized } = useVueFlow(flowId)

// ── View toggles ────────────────────────────────────────────────────────────
const vista = reactive<Vista>({ componente: null, modo: 'ley' })
const ver = reactive({ m: true, r: true, e: true })
const desbalanceEn = ref<'unidades' | 'pct'>('unidades')
// A saved balance was run before saving (v1 balances have no run history), so its reconciled values are real.
const corrida = computed(() => props.sesion.corrida || !!props.sesion.balance)
// Fine tonnage of a component: small plants need decimals to read at all (largest value < 10 → 2, < 100 → 1).
const decFino = computed(() =>
  props.sesion.componentes.map((_, k) => {
    const max = Math.max(0, ...props.sesion.flujos.map(f => {
      const x = valores(f, { componente: k, modo: 'fino' }, corrida.value)
      return Math.max(x.m, x.r)
    }))
    return max < 10 ? 2 : max < 100 ? 1 : 0
  }))
const dec = (v: Vista) => (v.componente === null ? 0 : v.modo === 'ley' ? 2 : decFino.value[v.componente] ?? 0)
// Sign of the shown (rounded) value, so a tiny residual reads 0 and not −0.
const signed = (n: number, d: number) => {
  const r = Number(n.toFixed(d))
  return `${r > 0 ? '+' : r < 0 ? '−' : ''}${fmt(Math.abs(n), d)}`
}

// ── Topology and layout ─────────────────────────────────────────────────────
const fs = computed(() => buildFlowsheet(props.sesion))
const ctx2d = document.createElement('canvas').getContext('2d')
// Labels are measured with Work Sans: wait for the faces the diagram uses (fallback metrics are narrower).
const fontsReady = ref(false)
onMounted(async () => {
  try {
    await Promise.all(['500 10.5px', '500 11px', '600 12px'].map(f => document.fonts.load(`${f} "Work Sans"`)))
  } catch {} // measured with the fallback font then
  fontsReady.value = true
})
function textWidth(text: string, font: string) {
  if (!ctx2d) return text.length * 6.6
  ctx2d.font = font
  return ctx2d.measureText(text).width
}
const FONT_NAME = '500 10.5px "Work Sans", system-ui, sans-serif'
const FONT_VALS = '500 11px "Work Sans", system-ui, sans-serif'
const FONT_UNIT = '600 12px "Work Sans", system-ui, sans-serif'
const CHIP_PAD = 16
const CHIP_H = 33

const allVistas = computed<Vista[]>(() => [
  { componente: null, modo: 'ley' },
  ...props.sesion.componentes.flatMap((_, k) => [{ componente: k, modo: 'ley' as const }, { componente: k, modo: 'fino' as const }]),
])
function partes(j: number, v: Vista, show: { m: boolean; r: boolean; e: boolean }): [string, string][] {
  const f = props.sesion.flujos[j]!
  const x = valores(f, v, corrida.value)
  const d = dec(v)
  const out: [string, string][] = []
  if (show.m || !corrida.value) out.push(['M', fmt(x.m, d)])
  if (corrida.value && show.r) out.push(['R', fmt(x.r, d)])
  if (corrida.value && show.e) out.push(['E', x.e === null ? '—' : `${signed(x.e, 2)} %`])
  return out
}
const partesWidth = (p: [string, string][]) => textWidth(p.map(([k, v]) => `${k} ${v}`).join(' · '), FONT_VALS)

// Room for the widest chip of every view, with digits widened, so toggling and small edits never move the layout.
const labelSizes = computed(() =>
  fontsReady.value && props.sesion.flujos.map((f, j) => {
    const vals = Math.max(...allVistas.value.map(v => partesWidth(partes(j, v, { m: true, r: true, e: true }).map(([k, s]) => [k, s.replace(/\d/g, '8')]))))
    return { width: Math.ceil(Math.max(textWidth(f.nombre, FONT_NAME), vals) + CHIP_PAD), height: CHIP_H }
  }),
)
const nodeSize = (n: FsNode): Size =>
  n.tipo === 'unidad' ? { width: Math.max(128, Math.ceil(textWidth(n.label, FONT_UNIT)) + 32), height: 48 }
    : n.tipo === 'stock' ? { width: 26, height: 22 } : { width: TERMINAL, height: TERMINAL }

const layout = shallowRef<Layout | null>(null)
/** The topology `layout` was computed from: nodes and edges are drawn from it, never from a newer one in flight. */
const lfs = shallowRef<Flowsheet | null>(null)
const keys = computed(() => layoutKeys(lfs.value?.nodes ?? []))
const layoutError = ref('')
// Canvas size: it fills the viewport below its own top, and the layout folds wide plants to its shape.
const wrap = ref<HTMLElement>()
const canvas = reactive({ w: 0, h: 0, alto: 416 })
const MIN_ALTO = 416
function medir() {
  const el = wrap.value
  if (!el) return
  if (!document.fullscreenElement) canvas.alto = Math.max(MIN_ALTO, Math.round(window.innerHeight - el.getBoundingClientRect().top - window.scrollY - 16))
  canvas.w = el.clientWidth
  canvas.h = document.fullscreenElement ? el.clientHeight : canvas.alto
}
onMounted(() => {
  medir()
  window.addEventListener('resize', medir)
})
onBeforeUnmount(() => window.removeEventListener('resize', medir))
// Re-layout only when the shape class changes, not on every pixel of a resize.
const aspecto = computed(() => (canvas.w && canvas.h ? Math.round((canvas.w / canvas.h) * 4) / 4 : null))

const signature = computed(() => JSON.stringify([fs.value.nodes.map(layoutKey), fs.value.edges.map(e => [e.source, e.target]), labelSizes.value && labelSizes.value.map(s => s.width), aspecto.value]))
let seq = 0
acquireElk()
onBeforeUnmount(() => {
  seq++ // a layout still in flight is dropped
  releaseElk()
})
watch(signature, async () => {
  const sizes = labelSizes.value
  if (aspecto.value === null || !sizes) return
  const mine = ++seq
  const f = fs.value
  try {
    // A long plant unrolled left to right is a thin strip; ELK can fold it into rows near the canvas shape.
    // Both are computed and the folded one wins only if it fits at a clearly larger zoom.
    const label = (e: FsEdge) => sizes[e.flujo]!
    const candidatos = await Promise.all([
      layoutFlowsheet(getElk(), f, nodeSize, label),
      layoutFlowsheet(getElk(), f, nodeSize, label, { 'elk.layered.wrapping.strategy': 'MULTI_EDGE', 'elk.aspectRatio': String(aspecto.value) }),
    ])
    const zoom = (c: Layout) => fitZoom(boundsOf(c), canvas.w, canvas.h)
    const l = candidatos.reduce((a, b) => (zoom(b) > zoom(a) * 1.15 ? b : a))
    if (mine !== seq) return
    layoutError.value = ''
    const primera = !layout.value
    lfs.value = f
    layout.value = l
    aplicarPosiciones({ refit: primera })
  } catch (e) {
    dropElk() // a crashed worker is replaced on the next layout
    if (mine === seq) layoutError.value = e instanceof Error ? e.message : 'No se pudo calcular la disposición.'
  }
}, { immediate: true })

// ── Saved layout per process ────────────────────────────────────────────────
interface Entrada {
  k: string
  x: number
  y: number
}
const guardado = shallowRef<Map<string, Pt> | null>(null)
const desdeEntradas = (e: Entrada[] | null) => (e?.length ? new Map(e.map(({ k, x, y }) => [k, { x, y }])) : null)
const pos = reactive<Record<string, Pt>>({})
const movido = ref(false)
const guardando = ref(false)
const flowNodes = shallowRef<Node[]>([])
let fitPending = false
const guardadoCargado = ref(false)
const nodosMedidos = ref(false)

async function cargarGuardado() {
  try {
    guardado.value = desdeEntradas((await api<{ layout: Entrada[] | null }>(`/v2/procesos/${props.sesion.proceso.id}/diagrama`)).layout)
  } catch {
    guardado.value = null // the automatic layout still works
  }
  if (!movido.value) aplicarPosiciones({ refit: true })
  guardadoCargado.value = true
}
onMounted(cargarGuardado)

/**
 * Node positions: unsaved drags first (they survive a re-layout), then the saved layout (nodes no longer in
 * the process are ignored), then ELK. Nodes new to a saved layout go below it, keeping ELK's order among them.
 * The view is refitted only on the first layout and on Restablecer.
 */
function aplicarPosiciones({ usarGuardado = true, refit = false } = {}) {
  const l = layout.value
  const f = lfs.value
  if (!l || !f) return
  const prev: Record<string, Pt> = movido.value && !refit ? { ...pos } : {}
  const saved = usarGuardado ? guardado.value : null
  const next = posiciones(f.nodes.map(n => n.id), l, prev, id => saved?.get(keys.value.get(id)!))
  for (const k of Object.keys(pos)) delete pos[k]
  Object.assign(pos, next)
  flowNodes.value = f.nodes.flatMap(n => {
    const r = l.nodes[n.id]
    return r ? [{ id: n.id, type: 'fs', position: { ...pos[n.id]! }, data: { tipo: n.tipo, width: r.width, height: r.height, flujo: n.flujo } }] : []
  })
  if (!Object.keys(prev).length) movido.value = false
  if (refit) fitPending = true
}
onNodesInitialized(() => {
  nodosMedidos.value = true
  if (!fitPending) return
  fitPending = false
  vistaInicial()
})

// Open readable: fit everything if that stays at 75 % or more, else 75 % anchored on the feeds (left), and the user pans.
const ZOOM_LEGIBLE = 0.75
function vistaInicial() {
  const l = layout.value
  if (!l) return
  // "Ver en diagrama" from Diagnóstico: center the pulsing stream's label at 100 %.
  const pulso = fs.value.edges.find(e => e.primary && e.flujo === store.flujoPulso)
  const lab = pulso && geom.value[pulso.id]?.label
  if (lab) return setViewport({ x: canvas.w / 2 - (lab.x + lab.width / 2), y: canvas.h / 2 - (lab.y + lab.height / 2), zoom: 1 })
  const b = boundsOf(l, pos, geom.value)
  if (fitZoom(b, canvas.w, canvas.h) >= ZOOM_LEGIBLE) return ajustar()
  const z = ZOOM_LEGIBLE
  const feeds = fs.value.nodes.filter(n => n.tipo === 'entrada').map(n => pos[n.id]!)
  const x0 = feeds.length ? Math.min(...feeds.map(p => p.x)) : b.x
  const y0 = feeds.length ? Math.min(...feeds.map(p => p.y)) : b.y
  setViewport({ x: 24 - x0 * z, y: 24 - Math.max(b.y, y0 - 60) * z, zoom: z })
}

function onDrag({ node }: NodeDragEvent) {
  pos[node.id] = { x: node.position.x, y: node.position.y }
  movido.value = true
}

async function guardarDisposicion() {
  guardando.value = true
  try {
    const body: Entrada[] = (lfs.value?.nodes ?? []).flatMap(n => (pos[n.id] ? [{ k: keys.value.get(n.id)!, ...pos[n.id]! }] : []))
    guardado.value = desdeEntradas((await api<{ layout: Entrada[] }>(`/v2/procesos/${props.sesion.proceso.id}/diagrama`, { method: 'PUT', body: { layout: body } })).layout)
    movido.value = false
    notify('ok', `Disposición guardada para ${props.sesion.proceso.nombre}.`)
  } catch (e) {
    notify('danger', errorMessage(e, 'No se pudo guardar la disposición.'))
  } finally {
    guardando.value = false
  }
}
async function restablecer() {
  if (guardado.value) {
    guardando.value = true
    try {
      await api(`/v2/procesos/${props.sesion.proceso.id}/diagrama`, { method: 'PUT', body: { layout: [] } })
      guardado.value = null
    } catch (e) {
      notify('danger', errorMessage(e, 'No se pudo restablecer la disposición.'))
      return
    } finally {
      guardando.value = false
    }
  }
  movido.value = false
  aplicarPosiciones({ usarGuardado: false, refit: true })
}

// ── Geometry: ELK routes, or a simple orthogonal route for edges of moved nodes ─
const geom = computed(() => {
  const l = layout.value
  const f = lfs.value
  const out: DiagramCtx['geom'] = {}
  if (!l || !f) return out
  const moved = (id: string) => {
    const p = pos[id]
    const r = l.nodes[id]
    return !!p && !!r && (Math.abs(p.x - r.x) > 0.5 || Math.abs(p.y - r.y) > 0.5)
  }
  for (const e of f.edges) {
    const base = l.edges[e.id]
    const ls = l.nodes[e.source]
    const lt = l.nodes[e.target]
    const ps = pos[e.source]
    const pt = pos[e.target]
    if (!base || !ls || !lt || !ps || !pt) continue
    // Without an ELK label a stream could not be reached from the keyboard: give it one on its longest segment.
    const conEtiqueta = (g: { points: Pt[]; label: DiagramCtx['geom'][string]['label'] }) =>
      e.primary && !g.label && g.points.length ? { ...g, label: labelOn(g.points, (labelSizes.value || [])[e.flujo] ?? { width: 120, height: CHIP_H }) } : g
    if (!moved(e.source) && !moved(e.target)) {
      out[e.id] = conEtiqueta(base)
      continue
    }
    const first = base.points[0] ?? { x: ls.x + ls.width, y: ls.y + ls.height / 2 }
    const last = base.points[base.points.length - 1] ?? { x: lt.x, y: lt.y + lt.height / 2 }
    const s = { x: ps.x + first.x - ls.x, y: ps.y + first.y - ls.y }
    const t = { x: pt.x + last.x - lt.x, y: pt.y + last.y - lt.y }
    const points = routeOrthogonal(s, t, Math.max(ps.y + ls.height, pt.y + lt.height))
    out[e.id] = conEtiqueta({ points, label: base.label ? labelOn(points, base.label) : null })
  }
  return out
})

// ── What each stream and node shows ─────────────────────────────────────────
const nombreVista = computed(() =>
  vista.componente === null ? 'Masa (TMS)' : `${vista.modo === 'ley' ? 'Ley' : 'Fino'} ${props.sesion.componentes[vista.componente]}`)
const chips = computed<Chip[]>(() =>
  props.sesion.flujos.map((f, j) => {
    const x = valores(f, vista, corrida.value)
    const p = partes(j, vista, ver)
    const d = dec(vista)
    const estado = x.estado === 'fuera' ? ', fuera de tolerancia' : x.estado === 'limite' ? ', en el límite de su tolerancia' : ''
    return {
      nombre: f.nombre,
      partes: p,
      tono: tono(x.estado),
      ancho: fontsReady.value ? Math.ceil(Math.max(textWidth(f.nombre, FONT_NAME), partesWidth(p)) + CHIP_PAD) : 0,
      aria: `${f.nombre}, ${nombreVista.value}: medido ${fmt(x.m, d)}${corrida.value ? `, reconciliado ${fmt(x.r, d)}, error ${x.e === null ? 'sin dato' : `${fmt(x.e, 2)} %`}` : ''}${estado}`,
    }
  }),
)
const nodos = computed(() => {
  const ds = desbalances(fs.value, props.sesion.flujos, vista, corrida.value)
  const unidad = vista.componente === null ? 't' : `t ${props.sesion.componentes[vista.componente]}`
  const out: Record<string, NodoVista> = {}
  props.sesion.nodos.forEach(n => {
    const d = ds[n.idx]!
    const txt = desbalanceEn.value === 'pct' ? (d.pct === null ? '—' : `${signed(d.pct, 2)} %`) : `${signed(d.unidades, 0)} ${unidad}`
    const label = n.nombre || `Nodo ${n.idx + 1}`
    const fuera = corrida.value && d.pct !== null && Math.abs(d.pct) > NODE_CLOSURE_TOLERANCE_PCT
    out[unitId(n.idx)] = {
      label,
      delta: `Δ ${txt}`,
      deltaTono: fuera ? 'fuera' : 'normal',
      aria: `Nodo ${label}, desbalance ${corrida.value ? 'reconciliado' : 'medido'} ${txt}${fuera ? ', fuera de la tolerancia de cierre' : ''}. Pulsa para resaltar sus flujos${props.editable ? '; flechas para moverlo' : ''}.`,
    }
  })
  return out
})
const conteo = computed(() => ({
  fuera: chips.value.filter(c => c.tono === 'fuera').length,
  limite: chips.value.filter(c => c.tono === 'limite').length,
}))

// ── Selection: a node highlights its streams (same colors as the Datos grid); a stream opens the panel ─
const flujoSel = ref<number | null>(null)
const hover = reactive({ flujo: null as number | null, x: 0, y: 0 })
const foco = computed(() => {
  const out: DiagramCtx['foco'] = {}
  if (store.nodoSel === null) return out
  const id = unitId(store.nodoSel)
  for (const e of fs.value.edges) out[e.id] = e.target === id ? 'in' : e.source === id ? 'out' : 'dim'
  return out
})
const nodosAtenuados = computed(() => {
  if (store.nodoSel === null) return new Set<string>()
  const id = unitId(store.nodoSel)
  const keep = new Set([id, ...fs.value.edges.flatMap(e => (e.source === id || e.target === id ? [e.source, e.target] : []))])
  return new Set(fs.value.nodes.filter(n => !keep.has(n.id)).map(n => n.id))
})

// Focus goes back to whatever opened the side panel when it closes.
let opener: HTMLElement | null = null
function cerrarPanel() {
  if (flujoSel.value === null) return
  flujoSel.value = null
  const el = opener
  opener = null
  nextTick(() => el?.isConnected && el.focus())
}
const NUDGE = 8
const ctx = reactive({
  geom, chips, nodos, foco, nodosAtenuados,
  sospechosos: computed(() => (store.overlaySospechosos ? store.flujosSospechosos : null)),
  pulso: computed(() => store.flujoPulso),
  flujoSel, flujoHover: computed(() => hover.flujo),
  nodoSelId: computed(() => (store.nodoSel === null ? null : unitId(store.nodoSel))),
  editable: computed(() => props.editable),
  abrirFlujo: (j: number) => {
    if (flujoSel.value === j) return cerrarPanel()
    if (flujoSel.value === null) opener = document.activeElement instanceof HTMLElement || document.activeElement instanceof SVGElement ? (document.activeElement as HTMLElement) : null
    flujoSel.value = j
    hover.flujo = null
  },
  /** Keyboard alternative to dragging (WCAG 2.5.7): arrows move a focused unit, Shift for bigger steps. */
  mover: (id: string, dx: number, dy: number) => {
    const p = pos[id]
    if (!props.editable || !p) return
    const np = { x: p.x + dx * NUDGE, y: p.y + dy * NUDGE }
    pos[id] = np
    const n = findNode(id)
    if (n) n.position = { ...np }
    movido.value = true
  },
  hover: (j: number | null, e?: PointerEvent) => {
    hover.flujo = j
    const r = wrap.value?.getBoundingClientRect()
    if (e && r) Object.assign(hover, { x: e.clientX - r.left, y: e.clientY - r.top })
  },
  elegirNodo: (id: string) => {
    const n = fs.value.nodes.find(x => x.id === id)
    if (n?.nodo === undefined) return
    store.nodoSel = store.nodoSel === n.nodo ? null : n.nodo
  },
}) as unknown as DiagramCtx
provide(DIAGRAM_CTX, ctx)

// Edges go in together with their nodes: Vue Flow drops edges whose nodes do not exist yet.
const flowEdges = computed(() => {
  const ids = new Set(flowNodes.value.map(n => n.id))
  return (lfs.value?.edges ?? [])
    .filter(e => ids.has(e.source) && ids.has(e.target))
    .map(e => ({ id: e.id, source: e.source, target: e.target, type: 'stream', data: { flujo: e.flujo, primary: e.primary, stock: e.stock } }))
})
const tooltip = computed(() => {
  if (hover.flujo === null) return null
  const f = props.sesion.flujos[hover.flujo]!
  const x = valores(f, vista, corrida.value)
  return { f, x, d: dec(vista) }
})
const panelFlujo = computed(() => (flujoSel.value === null ? null : props.sesion.flujos[flujoSel.value] ?? null))
function limpiar() {
  cerrarPanel()
  store.nodoSel = null
  store.flujoPulso = null
}

// ── View controls ───────────────────────────────────────────────────────────
// Own fit instead of Vue Flow's: its bounds ignore edge routes, and wrapped rows send long routes past the nodes.
function ajustar() {
  const l = layout.value
  if (!l) return fitView({ padding: 0.04, maxZoom: 1 })
  const b = boundsOf(l, pos, geom.value)
  const z = Math.min(1, fitZoom(b, canvas.w, canvas.h))
  setViewport({ x: (canvas.w - b.width * z) / 2 - b.x * z, y: (canvas.h - b.height * z) / 2 - b.y * z, zoom: z })
}
const full = ref(false)
const onFull = () => (full.value = document.fullscreenElement === wrap.value)
onMounted(() => document.addEventListener('fullscreenchange', onFull))
onBeforeUnmount(() => document.removeEventListener('fullscreenchange', onFull))
async function pantallaCompleta() {
  try {
    if (document.fullscreenElement) await document.exitFullscreen()
    else await wrap.value?.requestFullscreen?.()
  } catch {
    notify('danger', 'El navegador no permitió la pantalla completa.')
    return
  }
  setTimeout(() => (medir(), ajustar()), 150)
}
function onKey(e: KeyboardEvent) {
  if ((e.target as HTMLElement).closest('input, select, textarea, button')) return
  const pan: Record<string, Pt> = { ArrowLeft: { x: 60, y: 0 }, ArrowRight: { x: -60, y: 0 }, ArrowUp: { x: 0, y: 60 }, ArrowDown: { x: 0, y: -60 } }
  if (e.key === '+' || e.key === '=') zoomIn()
  else if (e.key === '-') zoomOut()
  else if (e.key === '0') ajustar()
  else if (e.key === '1') zoomTo(1)
  else if (e.key === 'Escape') limpiar()
  else if (pan[e.key]) panBy(pan[e.key]!)
  else return
  e.preventDefault()
}

const archivo = computed(() => img.nombreArchivo(props.sesion.proceso.nombre, props.sesion.balance?.id ?? `sesion${props.sesion.id}`))
const exportando = ref<'' | 'svg' | 'png'>('')
async function exportar(tipo: 'svg' | 'png') {
  if (!wrap.value) return
  exportando.value = tipo
  hover.flujo = null // the export shows the diagram, not the pointer's hover
  await nextTick()
  try {
    if (tipo === 'svg') img.descargar((await img.diagramSvg(wrap.value)).svg, `${archivo.value}.svg`)
    else img.descargar(await img.diagramPng(wrap.value, 2), `${archivo.value}.png`)
  } catch (e) {
    notify('danger', e instanceof Error ? e.message : 'No se pudo exportar el diagrama.')
  } finally {
    exportando.value = ''
  }
}

/**
 * PNG at 2× of the whole diagram for the Excel export, once it is laid out (saved positions applied) and Vue Flow
 * has measured the nodes. Works while the component is mounted off-screen.
 */
async function png(): Promise<Blob> {
  await hasta(() => layoutError.value || (layout.value && guardadoCargado.value && nodosMedidos.value), 30_000, 'El diagrama tardó demasiado en dibujarse.')
  if (layoutError.value || !wrap.value) throw new Error(`No se pudo dibujar el diagrama: ${layoutError.value}`)
  hover.flujo = null
  await nextTick()
  await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))) // edges follow the nodes a frame later
  return img.diagramPng(wrap.value, 2)
}
defineExpose({ png })

const seg = 'h-8 px-2.5 text-[0.8125rem] font-medium text-ink-muted hover:text-ink aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-sm rounded-[5px] disabled:opacity-45 disabled:hover:text-ink-muted'
const grupo = 'flex items-center gap-0.5 rounded-md bg-surface-2 p-0.5'
const icono = 'grid size-8 place-items-center rounded-md text-ink-muted hover:bg-surface-2 hover:text-ink disabled:opacity-45'
const sueltos = computed(() => fs.value.sueltos.map(j => props.sesion.flujos[j]!.nombre))
</script>

<template>
  <section aria-label="Diagrama de proceso" class="space-y-2">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2" role="toolbar" aria-label="Opciones del diagrama">
      <div :class="grupo" role="group" aria-label="Variable">
        <button type="button" :class="seg" :aria-pressed="vista.componente === null" @click="vista.componente = null">Masa</button>
        <button v-for="(c, k) in sesion.componentes" :key="c" type="button" :class="seg" :aria-pressed="vista.componente === k" @click="vista.componente = k">{{ c }}</button>
      </div>
      <div v-if="vista.componente !== null" :class="grupo" role="group" aria-label="Ley o fino">
        <button type="button" :class="seg" :aria-pressed="vista.modo === 'ley'" @click="vista.modo = 'ley'">Ley</button>
        <button type="button" :class="seg" :aria-pressed="vista.modo === 'fino'" title="Fino = TMS · ley / 100" @click="vista.modo = 'fino'">Fino</button>
      </div>
      <div :class="grupo" role="group" aria-label="Valores en las etiquetas">
        <button type="button" :class="seg" :aria-pressed="ver.m || !corrida" :disabled="!corrida" @click="ver.m = !ver.m">Medido</button>
        <button type="button" :class="seg" :aria-pressed="corrida && ver.r" :disabled="!corrida" @click="ver.r = !ver.r">Reconciliado</button>
        <button type="button" :class="seg" :aria-pressed="corrida && ver.e" :disabled="!corrida" @click="ver.e = !ver.e">Error relativo</button>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs text-ink-muted">Desbalance</span>
        <div :class="grupo" role="group" aria-label="Desbalance de los nodos">
          <button type="button" :class="seg" :aria-pressed="desbalanceEn === 'unidades'" @click="desbalanceEn = 'unidades'">Unidades</button>
          <button type="button" :class="seg" :aria-pressed="desbalanceEn === 'pct'" @click="desbalanceEn = 'pct'">%</button>
        </div>
      </div>
      <div v-if="store.diag" :class="grupo" role="group" aria-label="Capa del diagnóstico">
        <button
          type="button" :class="seg" :aria-pressed="store.overlaySospechosos"
          title="Marca los flujos con mediciones sospechosas según el diagnóstico"
          @click="store.overlaySospechosos = !store.overlaySospechosos; store.flujoPulso = null"
        >
          Sospechosos ({{ store.flujosSospechosos.size }})
        </button>
      </div>
      <div class="ml-auto flex items-center gap-1">
        <template v-if="editable && (movido || guardado)">
          <button v-if="movido" type="button" class="btn btn-primary h-8 px-3 text-[0.8125rem]" :disabled="guardando" @click="guardarDisposicion">
            <LoaderCircle v-if="guardando" class="size-4 animate-spin" aria-hidden="true" /><Save v-else class="size-4" aria-hidden="true" /> Guardar disposición
          </button>
          <button type="button" class="btn btn-quiet h-8 px-3 text-[0.8125rem]" :disabled="guardando" @click="restablecer">
            <RotateCcw class="size-4" aria-hidden="true" /> Restablecer automático
          </button>
          <span class="mx-1 h-5 w-px bg-line" aria-hidden="true" />
        </template>
        <button type="button" :class="icono" title="Exportar SVG" aria-label="Exportar SVG" :disabled="!layout || !!exportando" @click="exportar('svg')"><FileCode2 class="size-4" aria-hidden="true" /></button>
        <button type="button" :class="icono" title="Exportar PNG" aria-label="Exportar PNG" :disabled="!layout || !!exportando" @click="exportar('png')"><FileImage class="size-4" aria-hidden="true" /></button>
        <span class="mx-1 h-5 w-px bg-line" aria-hidden="true" />
        <button type="button" :class="icono" title="Alejar (−)" aria-label="Alejar" @click="zoomOut()"><Minus class="size-4" aria-hidden="true" /></button>
        <button type="button" :class="icono" title="Acercar (+)" aria-label="Acercar" @click="zoomIn()"><Plus class="size-4" aria-hidden="true" /></button>
        <button type="button" :class="icono" title="Ajustar a la pantalla (0)" aria-label="Ajustar a la pantalla" @click="ajustar"><Scan class="size-4" aria-hidden="true" /></button>
        <button type="button" :class="icono" :title="full ? 'Salir de pantalla completa' : 'Pantalla completa'" :aria-label="full ? 'Salir de pantalla completa' : 'Pantalla completa'" @click="pantallaCompleta">
          <Minimize v-if="full" class="size-4" aria-hidden="true" /><Maximize v-else class="size-4" aria-hidden="true" />
        </button>
      </div>
    </div>

    <p class="text-xs text-ink-muted">
      <template v-if="corrida">
        <b class="font-semibold text-ink">M</b> medido · <b class="font-semibold text-ink">R</b> reconciliado · <b class="font-semibold text-ink">E</b> error relativo.
        <template v-if="conteo.fuera">En <span class="font-semibold text-danger">rojo</span>, {{ conteo.fuera }} {{ conteo.fuera === 1 ? 'flujo fuera' : 'flujos fuera' }} de tolerancia{{ conteo.limite ? '; ' : '.' }}</template>
        <template v-if="conteo.limite">{{ conteo.fuera ? 'en' : 'En' }} <span class="font-semibold text-warn">ámbar</span>, {{ conteo.limite }} en el límite (≥ 80 % de su tolerancia).</template>
        <template v-if="!conteo.fuera && !conteo.limite">Todos los flujos están dentro de su tolerancia.</template>
      </template>
      <template v-else>Valores medidos (<b class="font-semibold text-ink">M</b>). Corre el balance para ver el reconciliado, el error y el uso de tolerancia.</template>
      <span class="hidden sm:inline"> Clic en un nodo resalta sus flujos; clic en un flujo abre su detalle.</span>
    </p>

    <Notice v-if="layoutError" tone="danger">No se pudo dibujar el diagrama: {{ layoutError }}</Notice>
    <Notice v-if="sueltos.length" tone="info">{{ sueltos.length === 1 ? 'Un flujo no tiene' : `${sueltos.length} flujos no tienen` }} nodos en la matriz y no se dibuja{{ sueltos.length === 1 ? '' : 'n' }}: {{ sueltos.join(', ') }}.</Notice>

    <div
      ref="wrap"
      class="fs-wrap relative overflow-hidden rounded-lg border border-line bg-surface"
      :class="{ 'fs-full': full }"
      :style="full ? undefined : { height: `${canvas.alto}px` }"
      @keydown="onKey"
    >
      <div v-if="!layout && !layoutError" class="absolute inset-0 grid place-items-center text-ink-muted" role="status">
        <span class="flex items-center gap-2"><LoaderCircle class="size-4 animate-spin" aria-hidden="true" /> Dibujando el diagrama…</span>
      </div>
      <VueFlow
        :id="flowId"
        :nodes="flowNodes"
        :edges="flowEdges"
        :min-zoom="0.15"
        :max-zoom="3"
        :nodes-draggable="editable"
        :nodes-connectable="false"
        :elements-selectable="false"
        :nodes-focusable="false"
        :edges-focusable="false"
        :zoom-on-double-click="false"
        :disable-keyboard-a11y="true"
        tabindex="0"
        aria-label="Lienzo del diagrama. Flechas para desplazar, + y − para el zoom, 0 para ajustar."
        @node-drag="onDrag"
        @pane-click="limpiar"
      >
        <template #node-fs="p"><FlowNode :id="p.id" :data="p.data" /></template>
        <template #edge-stream="p"><StreamEdge :id="p.id" :data="p.data" /></template>
      </VueFlow>

      <div
        v-if="tooltip"
        class="pointer-events-none absolute z-20 w-64 rounded-md border border-line bg-surface px-3 py-2 text-xs shadow-lg"
        :style="{ left: `${Math.min(hover.x + 14, (wrap?.clientWidth ?? 0) - 270)}px`, top: `${hover.y + 16}px` }"
        role="tooltip"
      >
        <p class="font-semibold text-ink">{{ tooltip.f.nombre }}</p>
        <p v-if="tooltip.f.tag || tooltip.f.descripcion" class="text-ink-muted">{{ [tooltip.f.tag, tooltip.f.descripcion].filter(Boolean).join(' · ') }}</p>
        <dl class="mt-1.5 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
          <dt class="text-ink-muted">Vista</dt><dd class="text-right">{{ nombreVista }}</dd>
          <dt class="text-ink-muted">Medido</dt><dd class="text-right">{{ fmt(tooltip.x.m, tooltip.d) }}</dd>
          <template v-if="corrida">
            <dt class="text-ink-muted">Reconciliado</dt><dd class="text-right">{{ fmt(tooltip.x.r, tooltip.d) }}</dd>
            <dt class="text-ink-muted">Error relativo</dt><dd class="text-right">{{ tooltip.x.e === null ? '—' : `${signed(tooltip.x.e, 2)} %` }}</dd>
            <template v-if="tooltip.x.tolInf !== null">
              <dt class="text-ink-muted">Tolerancia</dt><dd class="text-right">−{{ fmt(tooltip.x.tolInf, 2, 0) }} / +{{ fmt(tooltip.x.tolSup, 2, 0) }} %</dd>
            </template>
            <dt class="text-ink-muted">Uso de tolerancia</dt>
            <dd class="text-right" :class="{ 'font-semibold text-danger': tooltip.x.estado === 'fuera', 'font-semibold text-warn': tooltip.x.estado === 'limite' }">
              {{ tooltip.x.usoPct === null ? 'sin tolerancia acotada' : `${fmt(tooltip.x.usoPct, 0)} %` }}
            </dd>
          </template>
        </dl>
      </div>

      <FlujoPanel
        v-if="panelFlujo"
        :flujo="panelFlujo" :componentes="sesion.componentes" :corrida="corrida" :editable="editable"
        @close="cerrarPanel" @editar="emit('editar', panelFlujo.idx)"
      />
    </div>
  </section>
</template>

<style>
/* Flowsheet drawing. Unscoped: Vue Flow renders the node and edge slots inside its own tree. Export inlines these. */
.fs-wrap {
  background-color: var(--surface);
  background-image: radial-gradient(color-mix(in srgb, var(--ink-faint) 22%, transparent) 1px, transparent 1px);
  background-size: 20px 20px;
}
.fs-wrap.fs-full {
  height: 100dvh;
  border-radius: 0;
}
.fs-wrap .vue-flow__node {
  cursor: pointer;
}
.fs-wrap .vue-flow__node.draggable {
  cursor: grab;
}
.fs-handle {
  opacity: 0;
  pointer-events: none;
  min-width: 0 !important;
  min-height: 0 !important;
  width: 1px !important;
  height: 1px !important;
  border: 0 !important;
}
.fs-dim {
  opacity: 0.3;
  transition: opacity 150ms;
}

.fs-edge {
  cursor: pointer;
  transition: opacity 150ms;
}
.fs-hit {
  fill: none;
  stroke: transparent;
  stroke-width: 14;
}
.fs-line {
  fill: none;
  stroke: var(--ink-muted);
  stroke-width: 1.5;
  stroke-linejoin: round;
}
.fs-arrow {
  fill: var(--ink-muted);
  stroke: none;
}
.fs-edge.stock .fs-line {
  stroke-dasharray: 5 4;
}
.fs-edge.t-limite .fs-line {
  stroke: var(--warn);
  stroke-width: 2;
}
.fs-edge.t-limite .fs-arrow {
  fill: var(--warn);
}
.fs-edge.t-fuera .fs-line {
  stroke: var(--danger);
  stroke-width: 2;
}
.fs-edge.t-fuera .fs-arrow {
  fill: var(--danger);
}
.fs-edge.f-in .fs-line {
  stroke: var(--flow-in);
  stroke-width: 2.5;
}
.fs-edge.f-in .fs-arrow {
  fill: var(--flow-in);
}
.fs-edge.f-out .fs-line {
  stroke: var(--flow-out);
  stroke-width: 2.5;
}
.fs-edge.f-out .fs-arrow {
  fill: var(--flow-out);
}
.fs-edge.sosp .fs-line {
  stroke: var(--danger);
  stroke-width: 3;
}
.fs-edge.sosp .fs-arrow {
  fill: var(--danger);
}
.fs-edge.sosp .fs-chip-bg {
  fill: var(--danger-soft);
  stroke: var(--danger);
  stroke-width: 2;
}
.fs-edge.no-sosp {
  opacity: 0.35;
}
.fs-edge.pulso .fs-line,
.fs-edge.pulso .fs-chip-bg {
  animation: fs-pulso 1.1s ease-in-out infinite alternate;
}
@keyframes fs-pulso {
  from {
    stroke-width: 2;
  }
  to {
    stroke-width: 6;
  }
}
@media (prefers-reduced-motion: reduce) {
  .fs-edge.pulso .fs-line,
  .fs-edge.pulso .fs-chip-bg {
    animation: none;
    stroke-width: 5;
  }
}
.fs-edge.f-dim {
  opacity: 0.18;
}
.fs-edge.hot .fs-line {
  stroke-width: 2.5;
}
.fs-edge.sel .fs-line {
  stroke: var(--primary);
  stroke-width: 3;
}
.fs-edge.sel .fs-arrow {
  fill: var(--primary);
}

.fs-chip-bg {
  fill: var(--surface);
  stroke: var(--line-strong);
  stroke-width: 1;
}
.fs-chip-name {
  fill: var(--ink-muted);
  font-size: 10.5px;
  font-weight: 500;
}
.fs-chip-vals {
  fill: var(--ink);
  font-size: 11px;
  font-weight: 500;
}
.fs-chip-key,
.fs-chip-sep {
  fill: var(--ink-faint);
}
.fs-edge.t-limite .fs-chip-bg {
  fill: var(--warn-soft);
  stroke: var(--warn);
}
.fs-edge.t-limite .fs-chip-vals {
  fill: var(--warn);
  font-weight: 600;
}
.fs-edge.t-fuera .fs-chip-bg {
  fill: var(--danger-soft);
  stroke: var(--danger);
}
.fs-edge.t-fuera .fs-chip-vals {
  fill: var(--danger);
  font-weight: 600;
}
.fs-edge.sel .fs-chip-bg {
  stroke: var(--primary);
  stroke-width: 2;
}
.fs-chip:focus {
  outline: none;
}
.fs-chip:focus-visible .fs-chip-bg {
  stroke: var(--focus);
  stroke-width: 2.5;
}

.fs-unit {
  fill: var(--surface);
  stroke: var(--ink-muted);
  stroke-width: 1.5;
}
.fs-node:focus {
  outline: none;
}
.fs-node:focus-visible .fs-unit {
  stroke: var(--focus);
  stroke-width: 2.5;
}
.fs-unit-name {
  fill: var(--ink);
  font-size: 12px;
  font-weight: 600;
}
.fs-unit-delta {
  fill: var(--ink-muted);
  font-size: 11px;
}
.fs-unit-delta.t-fuera {
  fill: var(--danger);
  font-weight: 600;
}
.fs-port {
  fill: var(--surface-2);
  stroke: var(--ink-faint);
  stroke-width: 1.25;
}
.fs-port-arrow {
  fill: none;
  stroke: var(--ink-muted);
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.fs-pile {
  fill: color-mix(in srgb, var(--ink-faint) 18%, var(--surface));
  stroke: var(--ink-muted);
  stroke-width: 1.25;
  stroke-dasharray: 3 2;
  stroke-linejoin: round;
}
</style>
