import { describe, expect, it } from 'vitest'
import { bloques, edicionDePropuesta, flujoDePropuesta, type Propuesta } from '../asistente'
import type { Sospechoso } from '../diagnostico'
import type { Flujo } from '../types'

const v = (medido: number, tol: number) => ({ medido, reconciliado: medido, tol_inf: tol, tol_sup: tol, desv_inf: 0, desv_sup: 0 })
const flujo = (idx: number, nombre: string, tms: number): Flujo =>
  ({ idx, nombre, tag: null, descripcion: null, jerarquia: 1, stock: false, tms: v(tms, 10), leyes: [v(60, 2)] })
const s = { componentes: ['Fe T'], flujos: [flujo(0, 'Alim', 130), flujo(1, 'Stock', -80)], matriz: [[1, -1]] }
const p = (x: Partial<Propuesta>): Propuesta => ({ flujo: 'Alim', variable: 'tms', tipo: 'corregir_valor', valor: 100, motivo: 'm', ...x })

describe('bloques', () => {
  it('splits paragraphs and groups bullet and numbered lists', () => {
    expect(bloques('Primero esto.\ncontinúa aquí.\n\n- uno\n* dos\n1. tres\n2) cuatro\nCierre.')).toEqual([
      { tipo: 'p', texto: 'Primero esto. continúa aquí.' },
      { tipo: 'ul', items: ['uno', 'dos'] },
      { tipo: 'ol', items: ['tres', 'cuatro'] },
      { tipo: 'p', texto: 'Cierre.' },
    ])
  })

  it('keeps markup as plain text and drops markdown markers', () => {
    expect(bloques('## Revisa **F1** `ya`\n\n<img src=x onerror=alert(1)>')).toEqual([
      { tipo: 'p', texto: 'Revisa F1 ya' },
      { tipo: 'p', texto: '<img src=x onerror=alert(1)>' },
    ])
    expect(bloques('  \r\n\r\n ')).toEqual([])
  })
})

describe('edicionDePropuesta', () => {
  it('maps a correction to the measured value, keeping a stock sign', () => {
    expect(edicionDePropuesta(p({}), s)).toEqual({ flujo: 0, variable: 'tms', accion: 'corregir_valor', sugerido: 100, campo: 'medido', antes: 130, despues: 100 })
    expect(edicionDePropuesta(p({ flujo: 'Stock', valor: 90 }), s)).toMatchObject({ flujo: 1, campo: 'medido', antes: -80, despues: -90 })
    expect(edicionDePropuesta(p({ variable: 'ley_1', valor: 61.5 }), s)).toMatchObject({ campo: 'medido', antes: 60, despues: 61.5 })
  })

  it('maps a widening to both tolerances, from the value the measurement must reach', () => {
    expect(edicionDePropuesta(p({ tipo: 'ampliar_tolerancia', valor: 150 }), s)).toMatchObject({ campo: 'tolerancia', antes: 10, despues: 15.4 })
  })

  it('applies the server bounds: 3 × tolerance unless the diagnosis suggested it, and tolMax for widening', () => {
    expect(edicionDePropuesta(p({ valor: 85 }), s)).toEqual({ error: 'Cambia la medición 34,6 %, más que el máximo de 30,0 %.' })
    const sosp: Sospechoso = { flujo: 'Alim', tag: null, variable: 'tms', valor_sugerido: 85, z: 5, valor_medido: 130, reduccion_gamma: 1, accion: 'corregir_valor', equivalentes: [] }
    expect(edicionDePropuesta(p({ valor: 85 }), s, [sosp])).toMatchObject({ campo: 'medido', despues: 85 })
    expect(edicionDePropuesta(p({ tipo: 'ampliar_tolerancia', valor: 170 }), s)).toHaveProperty('error')
    const sinTol = { ...s, flujos: [{ ...flujo(0, 'Alim', 100), tms: v(100, 0) }, flujo(1, 'Stock', -80)] }
    expect(edicionDePropuesta(p({ valor: 150 }), sinTol)).toMatchObject({ despues: 150 })
    expect(edicionDePropuesta(p({ valor: 151 }), sinTol)).toHaveProperty('error')
  })

  it('finds the row by idx when names repeat', () => {
    const dup = [flujo(0, 'X', 1), flujo(1, 'X', 1)]
    expect(flujoDePropuesta(p({ flujo: 'X' }), dup)).toBeUndefined()
    expect(flujoDePropuesta(p({ flujo: 'X', idx: 1 }), dup)?.idx).toBe(1)
  })

  it('has nothing to apply for field checks, unknown flows or missing components', () => {
    expect(edicionDePropuesta(p({ tipo: 'revisar_medicion', valor: null }), s)).toBeNull()
    expect(edicionDePropuesta(p({ flujo: 'Nada' }), s)).toBeNull()
    expect(edicionDePropuesta(p({ variable: 'ley_2' }), s)).toBeNull()
    expect(edicionDePropuesta(p({ valor: null }), s)).toBeNull()
  })
})
