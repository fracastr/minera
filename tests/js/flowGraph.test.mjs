// Run with: node --test tests/js/*.test.mjs
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildFlowGraph } from '../../resources/js/src/views/balances/flowGraph.js'

// Matrix and TMS of datos_entrada 2541 (balance 53): 9 nodes × 30 flows, no cycles.
const matriz2541 = [
  [1, 1, 1, -1, 0, 0, 0, -1, 0, 0, 0, -1, 0, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
  [0, 0, 0, 1, 0, -1, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
  [0, 0, 0, 0, 0, 0, 0, 1, 1, -1, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, -1],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, -1, 0, 0, -1, 0, 0, -1, 0, 0, 0],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, -1, 0, 0, 0, 0, 0, 0, 0],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, -1, -1, 0, 0, 0, 0],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, -1, -1, 0],
]
const tms2541 = [0, 0, 17416.56, 0, 0, 0, 0, 7918.06, -632.5, 8550.56, 0, 9498.5, -2232, 11730.5, 0, 0, 0, 0,
  37307.94, 0, 0, 0, 0, 22664.01, 3970.26, 18693.75, 14643.93, 599.76, 14044.17, 0]
const nodos = (n) => Array.from({ length: n }, (_, i) => ({ Nodos: `N${i}` }))
const flujos = (tms) => tms.map((v, j) => ({ Flujos: `F${j}`, 'TMS balance': v.toLocaleString('en-US') }))

test('real balance 2541 builds an acyclic graph whose process nodes close', () => {
  const indicesStock = [[2, 5], [3, 9], [4, 13], [5, 16], [7, 22], [8, 25], [9, 28]]
  const g = buildFlowGraph({ matriz: matriz2541, balanceNodos: nodos(9), balancesTable: flujos(tms2541), indicesStock })
  assert.equal(g.recirculaciones.length, 0)
  assert.ok(g.links.every(l => l.value > 0))
  // Every process node with flows: inputs == outputs (the solver closed the balance).
  g.nodes.filter(n => !n.externo).forEach(({ name }) => {
    const sumIn = g.links.filter(l => l.target === name).reduce((s, l) => s + l.value, 0)
    const sumOut = g.links.filter(l => l.source === name).reduce((s, l) => s + l.value, 0)
    assert.ok(Math.abs(sumIn - sumOut) < 0.1, `${name}: ${sumIn} vs ${sumOut}`)
  })
  // Delta stock with negative TMS and +1 coefficient enters the node as a positive external input.
  assert.deepEqual(g.links.find(l => l.flujo === 'F8'), { source: 'F8', target: 'N2', value: 632.5, flujo: 'F8', tipo: 'stock' })
  // Whole plant closes: entradas + stock drawn = salidas + stock accumulated.
  const t = g.totales
  assert.ok(Math.abs(t.entradas + t.stockConsumo - t.salidas - t.stockAcumulacion) < 0.1, JSON.stringify(t))
  assert.ok(Math.abs(t.stockConsumo - (632.5 + 2232)) < 0.01)
  assert.equal(g.nodes.find(n => n.name === 'F2').tipo, 'entrada')
  assert.equal(g.nodes.find(n => n.name === 'N0').tipo, 'proceso')
  assert.ok(Math.abs(g.nodes.find(n => n.name === 'N2').value - (7918.06 + 632.5)) < 0.01)
})

test('internal, external and unused flows', () => {
  const g = buildFlowGraph({
    matriz: [[1, -1, 0, -1], [0, 1, 0, 0]],
    balanceNodos: [{ Nodos: 'Planta' }, { Nodos: 'Stock' }],
    balancesTable: [
      { Flujos: 'Alimentación', 'TMS balance': '1,000' },
      { Flujos: 'Concentrado', 'TMS balance': '600' },
      { Flujos: 'Sin uso', 'TMS balance': '50' },
      { Flujos: 'Planta', 'TMS balance': '400' }, // external sink named like a node
    ],
  })
  assert.deepEqual(g.links.map(l => [l.source, l.target, l.value]), [
    ['Alimentación', 'Planta', 1000],
    ['Planta', 'Stock', 600],
    ['Planta', 'Planta (2)', 400],
  ])
  assert.deepEqual(g.nodes.map(n => [n.name, n.externo]), [
    ['Alimentación', true], ['Planta', false], ['Stock', false], ['Planta (2)', true],
  ])
})

test('recirculations are removed from the Sankey links', () => {
  // Feed → Molienda → Flotación → Concentrado, Flotación → Molienda (recirculation).
  const g = buildFlowGraph({
    matriz: [
      [1, -1, 1, 0],
      [0, 1, -1, -1],
    ],
    balanceNodos: [{ Nodos: 'Molienda' }, { Nodos: 'Flotación' }],
    balancesTable: [
      { Flujos: 'Feed', 'TMS balance': 100 },
      { Flujos: 'Descarga', 'TMS balance': 130 },
      { Flujos: 'Retorno', 'TMS balance': 30 },
      { Flujos: 'Concentrado', 'TMS balance': 100 },
    ],
  })
  assert.deepEqual(g.recirculaciones.map(l => l.flujo), ['Retorno'])
  assert.deepEqual(g.links.map(l => l.flujo), ['Feed', 'Descarga', 'Concentrado'])
})

test('zero-TMS flows are skipped', () => {
  const g = buildFlowGraph({ matriz: [[1]], balanceNodos: [{ Nodos: 'A' }], balancesTable: [{ Flujos: 'X', 'TMS balance': '0' }] })
  assert.deepEqual([g.nodes, g.links, g.recirculaciones], [[], [], []])
})
