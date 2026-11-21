// Flowsheet geometry: ELK layered layout (left to right, orthogonal, one port per stream end) and a small
// orthogonal router for edges whose nodes were moved by hand (ELK cannot route around fixed positions).
import type { ELK, ElkExtendedEdge, ElkNode } from 'elkjs/lib/elk-api'
import type { Flowsheet, FsEdge, FsNode } from '@/domain/flowsheet'

export interface Pt {
  x: number
  y: number
}
export interface Size {
  width: number
  height: number
}
export interface Rect extends Pt, Size {}

export interface Layout {
  nodes: Record<string, Rect>
  edges: Record<string, { points: Pt[]; label: Rect | null }>
}

export const TERMINAL = 22

const ROOT_OPTIONS = {
  'elk.algorithm': 'layered',
  'elk.direction': 'RIGHT',
  'elk.edgeRouting': 'ORTHOGONAL',
  'elk.layered.spacing.nodeNodeBetweenLayers': '56',
  'elk.layered.spacing.edgeNodeBetweenLayers': '18',
  'elk.layered.spacing.edgeEdgeBetweenLayers': '12',
  'elk.spacing.nodeNode': '36',
  'elk.spacing.edgeNode': '18',
  'elk.spacing.edgeEdge': '12',
  'elk.spacing.edgeLabel': '4',
  // One layering for all parts of the plant: packed components lose their edge label offsets in elkjs.
  'elk.separateConnectedComponents': 'false',
  'elk.edgeLabels.placement': 'CENTER',
  'elk.layered.nodePlacement.strategy': 'NETWORK_SIMPLEX',
  'elk.layered.considerModelOrder.strategy': 'NODES_AND_EDGES',
  'elk.layered.crossingMinimization.forceNodeModelOrder': 'false',
  'elk.layered.mergeEdges': 'false',
  'elk.layered.cycleBreaking.strategy': 'DEPTH_FIRST',
}

export async function layoutFlowsheet(
  elk: ELK,
  fs: Flowsheet,
  nodeSize: (n: FsNode) => Size,
  labelSize: (e: FsEdge) => Size,
  options: Record<string, string> = {},
): Promise<Layout> {
  const children: ElkNode[] = fs.nodes.map(n => {
    const size = nodeSize(n)
    const ports = fs.edges.flatMap(e => [
      ...(e.source === n.id ? [{ id: `${e.id}>`, width: 1, height: 1, layoutOptions: { 'elk.port.side': 'EAST' } }] : []),
      ...(e.target === n.id ? [{ id: `${e.id}<`, width: 1, height: 1, layoutOptions: { 'elk.port.side': 'WEST' } }] : []),
    ])
    return {
      id: n.id,
      ...size,
      ports,
      layoutOptions: {
        'elk.portConstraints': 'FIXED_SIDE',
        'elk.spacing.portPort': '16',
        'elk.portAlignment.default': 'CENTER',
        'elk.nodeSize.constraints': 'PORTS MINIMUM_SIZE',
        'elk.nodeSize.minimum': `(${size.width},${size.height})`,
        ...(n.tipo === 'entrada' ? { 'elk.layered.layering.layerConstraint': 'FIRST' } : {}),
        ...(n.tipo === 'salida' ? { 'elk.layered.layering.layerConstraint': 'LAST' } : {}),
      },
    }
  })
  const edges: ElkExtendedEdge[] = fs.edges.map(e => ({
    id: e.id,
    sources: [`${e.id}>`],
    targets: [`${e.id}<`],
    labels: e.primary ? [{ id: `${e.id}:label`, text: e.id, ...labelSize(e), layoutOptions: { 'elk.edgeLabels.placement': 'CENTER' } }] : [],
  }))

  const out = await elk.layout({ id: 'root', layoutOptions: { ...ROOT_OPTIONS, ...options }, children, edges })

  const layout: Layout = { nodes: {}, edges: {} }
  for (const c of out.children ?? []) layout.nodes[c.id] = { x: c.x ?? 0, y: c.y ?? 0, width: c.width ?? 0, height: c.height ?? 0 }
  for (const e of (out.edges ?? []) as ElkExtendedEdge[]) {
    const s = e.sections?.[0]
    const l = e.labels?.[0]
    layout.edges[e.id] = {
      points: s ? [s.startPoint, ...(s.bendPoints ?? []), s.endPoint].map(p => ({ x: p.x, y: p.y })) : [],
      label: l ? { x: l.x ?? 0, y: l.y ?? 0, width: l.width ?? 0, height: l.height ?? 0 } : null,
    }
  }
  return layout
}

/**
 * Orthogonal path between two side ports: right out of the source, left into the target.
 * Backwards edges loop below both nodes. The label goes on the longest segment.
 */
export function routeOrthogonal(s: Pt, t: Pt, below: number): Pt[] {
  const gap = 16
  if (t.x - s.x >= 2 * gap) {
    const mid = Math.round((s.x + t.x) / 2)
    return s.y === t.y ? [s, t] : [s, { x: mid, y: s.y }, { x: mid, y: t.y }, t]
  }
  const y = below + 24
  return [s, { x: s.x + gap, y: s.y }, { x: s.x + gap, y }, { x: t.x - gap, y }, { x: t.x - gap, y: t.y }, t]
}

export function labelOn(points: Pt[], size: Size): Rect {
  let best = { a: points[0] ?? { x: 0, y: 0 }, b: points[0] ?? { x: 0, y: 0 }, len: -1 }
  for (let i = 1; i < points.length; i++) {
    const a = points[i - 1]!
    const b = points[i]!
    const len = Math.abs(a.x - b.x) + Math.abs(a.y - b.y)
    if (len > best.len) best = { a, b, len }
  }
  return { x: (best.a.x + best.b.x) / 2 - size.width / 2, y: (best.a.y + best.b.y) / 2 - size.height / 2, ...size }
}

/** SVG path through orthogonal points with small rounded corners. */
export function pathOf(points: Pt[], r = 5): string {
  if (points.length < 2) return ''
  let d = `M${points[0]!.x},${points[0]!.y}`
  for (let i = 1; i < points.length - 1; i++) {
    const p = points[i - 1]!
    const c = points[i]!
    const n = points[i + 1]!
    const k = Math.min(r, Math.hypot(c.x - p.x, c.y - p.y) / 2, Math.hypot(n.x - c.x, n.y - c.y) / 2)
    const toward = (a: Pt, b: Pt, len: number) => {
      const dist = Math.hypot(b.x - a.x, b.y - a.y) || 1
      return { x: a.x + ((b.x - a.x) / dist) * len, y: a.y + ((b.y - a.y) / dist) * len }
    }
    const a = toward(c, p, k)
    const b = toward(c, n, k)
    d += ` L${a.x},${a.y} Q${c.x},${c.y} ${b.x},${b.y}`
  }
  const last = points[points.length - 1]!
  return `${d} L${last.x},${last.y}`
}

/** Arrowhead triangle at the end of the path, pointing along its last segment. */
export function arrowAt(points: Pt[], size = 7): string {
  const t = points[points.length - 1]
  const p = points[points.length - 2]
  if (!t || !p) return ''
  const len = Math.hypot(t.x - p.x, t.y - p.y) || 1
  const ux = (t.x - p.x) / len
  const uy = (t.y - p.y) / len
  const bx = t.x - ux * size
  const by = t.y - uy * size
  const w = size * 0.55
  return `M${t.x},${t.y} L${bx - uy * w},${by + ux * w} L${bx + uy * w},${by - ux * w} Z`
}

/** Bounding box of the nodes, labels and edge routes (`edges` overrides the layout's routes, e.g. after a drag). */
export function boundsOf(l: Layout, pos: Record<string, Pt> = {}, edges: Layout['edges'] = l.edges): Rect {
  let x0 = Infinity
  let y0 = Infinity
  let x1 = -Infinity
  let y1 = -Infinity
  const grow = (r: Rect) => {
    x0 = Math.min(x0, r.x)
    y0 = Math.min(y0, r.y)
    x1 = Math.max(x1, r.x + r.width)
    y1 = Math.max(y1, r.y + r.height)
  }
  for (const [id, r] of Object.entries(l.nodes)) grow({ ...r, ...pos[id] })
  for (const e of Object.values(edges)) {
    if (e.label) grow(e.label)
    for (const p of e.points) grow({ ...p, width: 0, height: 0 })
  }
  return Number.isFinite(x0) ? { x: x0, y: y0, width: x1 - x0, height: y1 - y0 } : { x: 0, y: 0, width: 0, height: 0 }
}

/** Zoom that fits `b` in a w × h viewport. */
export const fitZoom = (b: Size, w: number, h: number, pad = 0.04) =>
  Math.min((w * (1 - 2 * pad)) / Math.max(b.width, 1), (h * (1 - 2 * pad)) / Math.max(b.height, 1))
