// Process graph from the balance matrix (port of the v1/v3 flowGraph.js to the v2 DTO).
// matriz is nodes × flows: +1 = the flow enters the node, −1 = it leaves. A flow with one end is a system
// input/output; recirculations (edges closing a loop) are split out so a Sankey (a DAG) can draw the rest.
import type { Flujo } from './types'

export type TipoFlujo = 'entrada' | 'salida' | 'stock' | 'interno'

export interface Edge {
  source: string
  target: string
  value: number
  flujo: string
  idx: number
  tipo: TipoFlujo
}

/** Flows entering and leaving one node: what v1 painted with paint_tables, computed locally. */
export function nodeStreams(matriz: number[][], nodo: number): { entran: number[]; salen: number[] } {
  const row = matriz[nodo] ?? []
  return {
    entran: row.flatMap((c, j) => (c > 0 ? [j] : [])),
    salen: row.flatMap((c, j) => (c < 0 ? [j] : [])),
  }
}

export function buildFlowGraph({ matriz, nodos, flujos }: { matriz: number[][]; nodos: string[]; flujos: Flujo[] }) {
  const nodeNames = nodos.map((n, i) => n || `Nodo ${i + 1}`)
  const isNode = (n: string) => nodeNames.includes(n)
  const used = new Set(nodeNames)
  const unique = (base: string) => {
    let name = base
    for (let k = 2; used.has(name); k += 1) name = `${base} (${k})`
    used.add(name)
    return name
  }

  const edges: Edge[] = []
  flujos.forEach((f, j) => {
    const into: number[] = []
    const out: number[] = []
    matriz.forEach((coefs, i) => {
      const c = coefs[j] ?? 0
      if (c > 0) into.push(i)
      else if (c < 0) out.push(i)
    })
    const value = Math.abs(f.tms.reconciliado)
    // shortcut: flows touching more than two nodes are not drawn; the current matrices never have them.
    if (!value || into.length > 1 || out.length > 1 || (!into.length && !out.length)) return
    const flujo = f.nombre || `Flujo ${j + 1}`
    const source = out.length ? nodeNames[out[0]!]! : unique(flujo)
    const target = into.length ? nodeNames[into[0]!]! : unique(flujo)
    const tipo: TipoFlujo = f.stock ? 'stock' : !out.length ? 'entrada' : !into.length ? 'salida' : 'interno'
    edges.push({ source, target, value, flujo, idx: f.idx, tipo })
  })

  // DFS from the entry points first, so the edges that close a loop are the ones flowing "back".
  const adj = new Map<string, number[]>()
  edges.forEach((e, k) => adj.set(e.source, [...(adj.get(e.source) ?? []), k]))
  const hasInput = new Set(edges.map(e => e.target))
  const names = [...new Set(edges.flatMap(e => [e.source, e.target]))]
  const order = [...names.filter(n => !hasInput.has(n)), ...names.filter(n => hasInput.has(n))]
  const state = new Map<string, 1 | 2>() // 1 = on the DFS stack, 2 = done
  const back = new Set<number>()
  const visit = (start: string) => {
    const stack: [string, number][] = [[start, 0]]
    state.set(start, 1)
    while (stack.length) {
      const top = stack[stack.length - 1]!
      const outgoing = adj.get(top[0]) ?? []
      if (top[1] >= outgoing.length) {
        state.set(top[0], 2)
        stack.pop()
      } else {
        const k = outgoing[top[1]]!
        top[1] += 1
        const t = edges[k]!.target
        if (state.get(t) === 1) back.add(k)
        else if (!state.has(t)) {
          state.set(t, 1)
          stack.push([t, 0])
        }
      }
    }
  }
  order.forEach(n => {
    if (!state.has(n)) visit(n)
  })

  const links = edges.filter((_, k) => !back.has(k))
  const recirculaciones = edges.filter((_, k) => back.has(k))
  const linked = new Set(links.flatMap(e => [e.source, e.target]))
  const tipoNodo = new Map(edges.filter(e => e.tipo !== 'interno').map(e => [isNode(e.source) ? e.target : e.source, e.tipo]))
  const nodes = names
    .filter(n => linked.has(n))
    .map(name => ({
      name,
      externo: !isNode(name),
      tipo: isNode(name) ? ('proceso' as const) : tipoNodo.get(name),
      value: Math.max(
        links.filter(l => l.target === name).reduce((acc, l) => acc + l.value, 0),
        links.filter(l => l.source === name).reduce((acc, l) => acc + l.value, 0),
      ),
    }))
  const sum = (pred: (e: Edge) => boolean) => edges.filter(pred).reduce((acc, e) => acc + e.value, 0)
  const totales = {
    entradas: sum(e => e.tipo === 'entrada'),
    salidas: sum(e => e.tipo === 'salida'),
    // A delta flowing into a node means the stock was drawn down; out of a node, it accumulated.
    stockConsumo: sum(e => e.tipo === 'stock' && !isNode(e.source)),
    stockAcumulacion: sum(e => e.tipo === 'stock' && !isNode(e.target)),
  }
  return { nodes, links, recirculaciones, totales }
}
