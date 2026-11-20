// Run with: node --test tests/js/*.test.mjs
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { computeQuality, toNumber } from '../../resources/js/src/views/balances/reconciliationQuality.js'

const balancesTable = [
  { Flujos: 'Alimentación', 'TMS medido': '1,000', 'TMS balance': '1,040', 'Fet [%] Medido': '60.00', 'Fet [%] Balance': '59.40' },
  { Flujos: 'Concentrado', 'TMS medido': '600', 'TMS balance': '600', 'Fet [%] Medido': '66.00', 'Fet [%] Balance': '66.00' },
  { Flujos: 'Relave', 'TMS medido': '400', 'TMS balance': '440', 'Fet [%] Medido': '50.00', 'Fet [%] Balance': '50.00' },
]
const restriccionesTable = [
  { 'TMS Inf a[%]': 5, 'TMS Sup a[%]': 5, 'Fet [%] aInf': 2, 'Fet [%] aSup': 2, Jerarquia: 1 },
  { 'TMS Inf a[%]': 5, 'TMS Sup a[%]': 5, 'Fet [%] aInf': 2, 'Fet [%] aSup': 2, Jerarquia: 1 },
  { 'TMS Inf a[%]': 5, 'TMS Sup a[%]': 5, 'Fet [%] aInf': 2, 'Fet [%] aSup': 2, Jerarquia: 2 },
]
// One node: Alimentación in, Concentrado + Relave out.
const matriz = [[1, -1, -1]]

test('parses formatted numbers', () => {
  assert.equal(toNumber('1,234.5'), 1234.5)
  assert.equal(toNumber(''), null)
})

test('classifies tolerance usage per flow and variable', () => {
  const q = computeQuality({ balancesTable, restriccionesTable, balanceNodos: [{ Nodos: 'Planta', TMS: '0' }], matriz })
  const alimTms = q.flows.find(f => f.flujo === 'Alimentación' && f.variable === 'TMS')
  assert.equal(Math.round(alimTms.ajustePct), 4)
  assert.equal(Math.round(alimTms.usoPct), 80)
  assert.equal(alimTms.estado, 'limite')

  const alimFe = q.flows.find(f => f.flujo === 'Alimentación' && f.variable === 'FeT')
  assert.equal(alimFe.estado, 'ok') // -1% of a 2% tolerance

  const relave = q.flows.find(f => f.flujo === 'Relave' && f.variable === 'TMS')
  assert.equal(relave.estado, 'sin-tolerancia') // jerarquía 2: solver limits not shown
  assert.equal(relave.usoPct, null)

  assert.equal(q.flows[0].flujo, 'Alimentación') // highest usage first
  assert.equal(q.summary.enLimite, 1)
  assert.equal(q.summary.fuera, 0)
  assert.equal(q.summary.mayorAjusteTms.flujo, 'Relave')
})

test('flags flows beyond tolerance', () => {
  const restr = restriccionesTable.map(r => ({ ...r, 'TMS Sup a[%]': 2 }))
  const q = computeQuality({ balancesTable, restriccionesTable: restr, balanceNodos: [], matriz: [] })
  assert.equal(q.flows.find(f => f.flujo === 'Alimentación' && f.variable === 'TMS').estado, 'fuera')
})

test('measures node closure relative to throughput', () => {
  const closed = computeQuality({ balancesTable, restriccionesTable, balanceNodos: [{ Nodos: 'Planta', TMS: '0' }], matriz })
  assert.equal(closed.summary.nodosSinCerrar, 0)

  const open = computeQuality({ balancesTable, restriccionesTable, balanceNodos: [{ Nodos: 'Planta', TMS: '-52' }], matriz })
  assert.equal(Math.round(open.nodes[0].cierrePct * 10) / 10, 5) // 52 / 1040
  assert.equal(open.summary.nodosSinCerrar, 1)
})
