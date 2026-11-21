import { describe, expect, it } from 'vitest'
import { bloques, edicionDePropuesta, type Propuesta } from '../asistente'
import type { Flujo } from '../types'

const v = (medido: number, tol: number) => ({ medido, reconciliado: medido, tol_inf: tol, tol_sup: tol, desv_inf: 0, desv_sup: 0 })
const flujo = (idx: number, nombre: string, tms: number): Flujo =>
  ({ idx, nombre, tag: null, descripcion: null, jerarquia: 1, stock: false, tms: v(tms, 5), leyes: [v(60, 2)] })
const s = { componentes: ['Fe T'], flujos: [flujo(0, 'Alim', 130), flujo(1, 'Stock', -80)] }
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
    expect(edicionDePropuesta(p({ tipo: 'ampliar_tolerancia', valor: 117 }), s)).toMatchObject({ campo: 'tolerancia', antes: 5, despues: 10 })
  })

  it('has nothing to apply for field checks, unknown flows or missing components', () => {
    expect(edicionDePropuesta(p({ tipo: 'revisar_medicion', valor: null }), s)).toBeNull()
    expect(edicionDePropuesta(p({ flujo: 'Nada' }), s)).toBeNull()
    expect(edicionDePropuesta(p({ variable: 'ley_2' }), s)).toBeNull()
    expect(edicionDePropuesta(p({ valor: null }), s)).toBeNull()
  })
})
