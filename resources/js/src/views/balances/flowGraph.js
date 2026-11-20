// Sankey data from the balance matrix (nodes × flows, +1 = flow enters the node, -1 = leaves it).
// Link value is |TMS balance|; the coefficient gives the direction (stock deltas already carry it).
// echarts' Sankey only accepts a DAG, so recirculations (back edges) are moved to a separate list.
import { toNumber } from './reconciliationQuality.js'

export function buildFlowGraph({ matriz = [], balanceNodos = [], balancesTable = [] }) {
  const nodeNames = balanceNodos.map((n, i) => String(n.Nodos || `Nodo ${i + 1}`))
  const used = new Set(nodeNames)
  const unique = base => {
    let name = base
    for (let k = 2; used.has(name); k += 1) name = `${base} (${k})`
    used.add(name)
    return name
  }

  const edges = []
  balancesTable.forEach((row, j) => {
    const into = []
    const out = []
    matriz.forEach((coefs, i) => {
      const c = toNumber(coefs[j])
      if (c > 0) into.push(i)
      else if (c < 0) out.push(i)
    })
    const value = Math.abs(toNumber(row['TMS balance']) || 0)
    // shortcut: flows touching more than two nodes are not drawn; the current matrices never have them.
    if (!value || into.length > 1 || out.length > 1 || (!into.length && !out.length)) return
    const flujo = String(row.Flujos || `Flujo ${j + 1}`)
    const source = out.length ? nodeNames[out[0]] : unique(flujo)
    const target = into.length ? nodeNames[into[0]] : unique(flujo)
    edges.push({ source, target, value, flujo })
  })

  // DFS from the entry points first, so the edges that close a loop are the ones flowing "back".
  const adj = new Map()
  edges.forEach((e, k) => {
    if (!adj.has(e.source)) adj.set(e.source, [])
    adj.get(e.source).push(k)
  })
  const hasInput = new Set(edges.map(e => e.target))
  const names = [...new Set(edges.flatMap(e => [e.source, e.target]))]
  const order = [...names.filter(n => !hasInput.has(n)), ...names.filter(n => hasInput.has(n))]
  const state = new Map() // 1 = on the DFS stack, 2 = done
  const back = new Set()
  const visit = start => {
    const stack = [[start, 0]] // [node, next outgoing edge]
    state.set(start, 1)
    while (stack.length) {
      const top = stack[stack.length - 1]
      const outgoing = adj.get(top[0]) || []
      if (top[1] >= outgoing.length) {
        state.set(top[0], 2)
        stack.pop()
      } else {
        const k = outgoing[top[1]]
        top[1] += 1
        const t = edges[k].target
        if (state.get(t) === 1) back.add(k)
        else if (!state.has(t)) {
          state.set(t, 1)
          stack.push([t, 0])
        }
      }
    }
  }
  order.forEach(n => { if (!state.has(n)) visit(n) })

  const links = edges.filter((_, k) => !back.has(k))
  const recirculaciones = edges.filter((_, k) => back.has(k))
  const linked = new Set(links.flatMap(e => [e.source, e.target]))
  const nodes = names.filter(n => linked.has(n)).map(name => ({ name, externo: !nodeNames.includes(name) }))
  return { nodes, links, recirculaciones }
}
