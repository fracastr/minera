// Reconciliation quality computed from the tables the API already returns.
// Mirrors the solver: flows with jerarquía 1 are bounded by the "a[%]" tolerances
// of the restrictions table; other flows use limits not shown in the UI.

export const NEAR_LIMIT_PCT = 80
export const NODE_CLOSURE_TOLERANCE_PCT = 0.1

const VARIABLES = [
  { key: 'TMS', medido: 'TMS medido', balance: 'TMS balance', tolInf: 'TMS Inf a[%]', tolSup: 'TMS Sup a[%]' },
  { key: 'FeT', medido: 'Fet [%] Medido', balance: 'Fet [%] Balance', tolInf: 'Fet [%] aInf', tolSup: 'Fet [%] aSup' },
  { key: 'FeMag', medido: 'FeMag [%] Medido', balance: 'FeMag [%] Balance', tolInf: 'FeMag [%] aInf', tolSup: 'FeMag [%] aSup' },
]

export function toNumber(value) {
  if (value === null || value === undefined || value === '') return null
  const n = typeof value === 'number' ? value : parseFloat(String(value).replace(/,/g, ''))
  return Number.isFinite(n) ? n : null
}

function flowStatus(usoPct) {
  if (usoPct === null) return 'sin-tolerancia'
  if (usoPct > 100.5) return 'fuera'
  if (usoPct >= NEAR_LIMIT_PCT) return 'limite'
  return 'ok'
}

export function flowAdjustments(balancesTable = [], restriccionesTable = []) {
  const rows = []
  balancesTable.forEach((row, i) => {
    const restr = restriccionesTable[i] || {}
    const jerarquia = toNumber(restr.Jerarquia)
    VARIABLES.forEach(v => {
      if (!(v.medido in row)) return
      const medido = toNumber(row[v.medido])
      const balance = toNumber(row[v.balance])
      if (medido === null || balance === null || medido === 0) return

      const ajustePct = ((balance - medido) / Math.abs(medido)) * 100
      const tolInf = toNumber(restr[v.tolInf])
      const tolSup = toNumber(restr[v.tolSup])
      const tol = ajustePct >= 0 ? tolSup : tolInf
      const usoPct = jerarquia === 1 && tol > 0 ? (Math.abs(ajustePct) / tol) * 100 : null

      rows.push({
        flujo: row.Flujos,
        variable: v.key,
        medido,
        balance,
        ajustePct,
        tolInf: jerarquia === 1 ? tolInf : null,
        tolSup: jerarquia === 1 ? tolSup : null,
        usoPct,
        estado: flowStatus(usoPct),
      })
    })
  })
  return rows
}

// Node throughput = (inputs + outputs) / 2, independent of the matrix sign convention.
export function nodeClosures(balanceNodos = [], matriz = [], balancesTable = []) {
  const tms = balancesTable.map(row => toNumber(row['TMS balance']) || 0)
  return balanceNodos.map((node, i) => {
    const residual = toNumber(node.TMS) || 0
    const coefs = matriz[i] || []
    const throughput = coefs.reduce((sum, c, j) => sum + Math.abs(toNumber(c) || 0) * Math.abs(tms[j] || 0), 0) / 2
    const cierrePct = throughput > 0 ? (Math.abs(residual) / throughput) * 100 : null
    return {
      nodo: node.Nodos,
      residual,
      cierrePct,
      cierra: cierrePct === null ? Math.abs(residual) < 1 : cierrePct <= NODE_CLOSURE_TOLERANCE_PCT,
    }
  })
}

export function computeQuality({ balancesTable, restriccionesTable, balanceNodos, matriz }) {
  const flows = flowAdjustments(balancesTable, restriccionesTable)
  const nodes = nodeClosures(balanceNodos, matriz, balancesTable)
  const tmsFlows = flows.filter(f => f.variable === 'TMS')
  const maxTms = tmsFlows.reduce((max, f) => (!max || Math.abs(f.ajustePct) > Math.abs(max.ajustePct) ? f : max), null)

  return {
    flows: flows.slice().sort((a, b) => (b.usoPct === null ? -1 : b.usoPct) - (a.usoPct === null ? -1 : a.usoPct)
      || Math.abs(b.ajustePct) - Math.abs(a.ajustePct)),
    nodes,
    summary: {
      totalFlujos: balancesTable ? balancesTable.length : 0,
      enLimite: flows.filter(f => f.estado === 'limite').length,
      fuera: flows.filter(f => f.estado === 'fuera').length,
      mayorAjusteTms: maxTms,
      nodosSinCerrar: nodes.filter(n => !n.cierra).length,
      totalNodos: nodes.length,
    },
  }
}
