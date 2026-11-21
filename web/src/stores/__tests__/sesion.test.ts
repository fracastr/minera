import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Sesion } from '@/domain/types'

const api = vi.fn()
const ask = vi.fn()
vi.mock('@/lib/api', () => ({ api: (...a: unknown[]) => api(...a), errorMessage: (_e: unknown, f: string) => f }))
vi.mock('@/lib/feedback', async orig => ({ ...(await orig<object>()), ask: (...a: unknown[]) => ask(...a) }))

const { useSesionStore } = await import('../sesion')
const { toasts } = await import('@/lib/feedback')

const sesion = (id: number, medido = 100, balance: Sesion['balance'] = null): Sesion => ({
  id, balance, proceso: { id: 7, nombre: 'Pleito', valle: { id: 3, nombre: 'Elqui' } }, componentes: ['FeT'], corrida: false,
  flujos: [{ idx: 0, nombre: 'F', tag: null, descripcion: null, jerarquia: 1, stock: false,
    tms: { medido, reconciliado: medido, tol_inf: 5, tol_sup: 5, desv_inf: 0, desv_sup: 0 },
    leyes: [{ medido: 60, reconciliado: 60, tol_inf: 2, tol_sup: 2, desv_inf: 0, desv_sup: 0 }] }],
  nodos: [], matriz: [], indices_stock: [], inventarios: [], updated_at: null,
})

function deferred<T>() {
  let resolve!: (v: T) => void
  const promise = new Promise<T>(r => (resolve = r))
  return { promise, resolve }
}

beforeEach(() => {
  setActivePinia(createPinia())
  api.mockReset()
  ask.mockReset()
  toasts.splice(0)
})

describe('sesion store', () => {
  it('ignores a slower earlier load when another session was opened meanwhile', async () => {
    const store = useSesionStore()
    const first = deferred<Sesion>()
    api.mockReturnValueOnce(first.promise).mockResolvedValueOnce(sesion(2))
    const a = store.load(1)
    await store.load(2)
    first.resolve(sesion(1))
    await a
    expect(store.sesion?.id).toBe(2)
  })

  it('keeps edits typed while Correr was in flight and says so', async () => {
    const store = useSesionStore()
    api.mockResolvedValueOnce(sesion(1))
    await store.load(1)
    const run = deferred<Sesion>()
    api.mockReturnValueOnce(run.promise)
    const correr = store.correr()
    await Promise.resolve()
    store.sesion!.flujos[0]!.tms.medido = 999 // the grid wrote a new value
    await store.edited()
    run.resolve(sesion(1, 123))
    await correr
    expect(store.sesion!.flujos[0]!.tms.medido).toBe(999)
    expect(store.dirty).toBe(true)
    expect(toasts.at(-1)?.message).toContain('Vuelve a correr')
  })

  it('applies the run result when nothing changed meanwhile', async () => {
    const store = useSesionStore()
    api.mockResolvedValueOnce(sesion(1)).mockResolvedValueOnce(sesion(1, 123))
    await store.load(1)
    await store.correr()
    expect(store.sesion!.flujos[0]!.tms.reconciliado).toBe(123)
    expect(store.dirty).toBe(false)
  })

  it('asks once for concurrent writes on a reopened balance, and only marks dirty after the answer', async () => {
    const store = useSesionStore()
    api.mockResolvedValueOnce(sesion(1, 100, { id: 9, nombre: 'Nov', tipo: 'mensual', periodo_inicio: null, periodo_fin: null }))
    await store.load(1)
    const answer = deferred<string>()
    ask.mockReturnValue(answer.promise)
    const a = store.edited()
    const b = store.edited()
    expect(store.dirty).toBe(false)
    answer.resolve('sobrescribir')
    await Promise.all([a, b])
    expect(ask).toHaveBeenCalledTimes(1)
    expect(store.dirty).toBe(true)
  })

  it('always saves the grid (PUT datos) before the balance, even when not dirty', async () => {
    const store = useSesionStore()
    api.mockResolvedValueOnce(sesion(1))
    await store.load(1)
    api.mockResolvedValueOnce(sesion(1)).mockResolvedValueOnce({ balance_id: 5 }).mockResolvedValueOnce(sesion(1))
    const id = await store.guardar({ nombre: 'N', tipo: 'mensual', periodo_inicio: '2026-11-01', periodo_fin: '2026-11-30' })
    expect(id).toBe(5)
    expect(api.mock.calls.slice(1).map(c => [c[0], c[1]?.method])).toEqual([
      ['/v2/sesiones/1/datos', 'PUT'], ['/v2/sesiones/1/guardar', 'POST'], ['/v2/sesiones/1', undefined],
    ])
  })

  it('keeps dirty when saving fails', async () => {
    const store = useSesionStore()
    api.mockResolvedValueOnce(sesion(1))
    await store.load(1)
    await store.edited()
    api.mockRejectedValueOnce(new Error('500'))
    expect(await store.guardar({ nombre: 'N', tipo: 'mensual', periodo_inicio: '2026-11-01', periodo_fin: '2026-11-30' })).toBeUndefined()
    expect(store.dirty).toBe(true)
    expect(toasts.at(-1)?.tone).toBe('danger')
  })

  it('asks before restoring over unsaved edits', async () => {
    const store = useSesionStore()
    api.mockResolvedValueOnce(sesion(1))
    await store.load(1)
    await store.edited()
    ask.mockResolvedValueOnce(null)
    expect(await store.restaurar(3)).toBe(false)
    expect(api).toHaveBeenCalledTimes(1)
  })

  it('reset forgets the session so re-entry reloads', async () => {
    const store = useSesionStore()
    api.mockResolvedValueOnce(sesion(1))
    await store.load(1)
    await store.edited()
    store.reset()
    expect([store.sesion, store.dirty]).toEqual([null, false])
  })
})
