import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Sesion } from '@/domain/types'

const api = vi.fn()
const raw = vi.fn()
const ask = vi.fn()
const descargar = vi.fn()
vi.mock('@/lib/api', async () => {
  const { FetchError } = await import('ofetch')
  return {
    api: Object.assign((...a: unknown[]) => api(...a), { raw: (...a: unknown[]) => raw(...a) }),
    errorMessage: (e: unknown, f: string) => (e instanceof FetchError && typeof e.data?.message === 'string' ? e.data.message : f),
  }
})
vi.mock('@/composables/useDiagramImage', () => ({ descargar: (...a: unknown[]) => descargar(...a) }))
vi.mock('@/lib/feedback', async orig => ({ ...(await orig<object>()), ask: (...a: unknown[]) => ask(...a) }))

const { useSesionStore } = await import('../sesion')
const { toasts } = await import('@/lib/feedback')

const sesion = (id: number, medido = 100, balance: Sesion['balance'] = null): Sesion => ({
  id, balance, proceso: { id: 7, nombre: 'Pleito', valle: { id: 3, nombre: 'Elqui' } }, componentes: ['FeT'], corrida: false, solver: null,
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
  raw.mockReset()
  descargar.mockReset()
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

  it('applies a suggestion like a grid edit: asks on a saved balance, keeps the stock sign, marks dirty, does not run', async () => {
    const store = useSesionStore()
    const s = sesion(1, -130, { id: 9, nombre: 'Nov', tipo: 'mensual', periodo_inicio: null, periodo_fin: null })
    api.mockResolvedValueOnce(s)
    await store.load(1)
    ask.mockResolvedValueOnce(null) // cancelled: nothing changes
    expect(await store.aplicarSugerencia(0, 'tms', 'corregir_valor', 100)).toBe(false)
    expect([store.sesion!.flujos[0]!.tms.medido, store.dirty]).toEqual([-130, false])

    ask.mockResolvedValueOnce('sobrescribir')
    expect(await store.aplicarSugerencia(0, 'tms', 'corregir_valor', 100)).toBe(true)
    expect([store.sesion!.flujos[0]!.tms.medido, store.dirty]).toEqual([-100, true])
    expect(toasts.at(-1)?.message).toBe('Valor aplicado; corre el balance para ver el efecto.')

    await store.aplicarSugerencia(0, 'ley_1', 'ampliar_tolerancia', 63.1) // 5,17 % off a 2 % tolerance
    expect(store.sesion!.flujos[0]!.leyes[0]).toMatchObject({ medido: 60, tol_inf: 5.2, tol_sup: 5.2 })
    expect(api).toHaveBeenCalledTimes(1) // never ran
  })

  it('loads the diagnosis once per session version and says when the service is unavailable', async () => {
    const { FetchError } = await import('ofetch')
    const store = useSesionStore()
    api.mockResolvedValueOnce({ ...sesion(1), updated_at: 'v1' })
    await store.load(1)
    const unavailable = Object.assign(new FetchError('503'), { status: 503 })
    api.mockRejectedValueOnce(unavailable).mockResolvedValueOnce({ diagnostico: { sospechosos: [{ flujo: 'F', tag: null, variable: 'tms', equivalentes: [] }] }, tendencia: {} })
    await store.cargarDiagnostico()
    expect(store.diagError).toBe('Diagnóstico no disponible (servicio en actualización).')
    await store.cargarDiagnostico()
    await store.cargarDiagnostico()
    expect(api).toHaveBeenCalledTimes(3)
    expect([...store.flujosSospechosos]).toEqual([0])
    store.descartados.add('F|tms')
    expect(store.flujosSospechosos.size).toBe(0)
  })

  describe('exportar', () => {
    const respuesta = (headers: Record<string, string>) => ({ headers: new Headers(headers), _data: new Blob(['xlsx']) })
    // jsdom's Blob has no text() (browsers do).
    Blob.prototype.text ??= function (this: Blob) {
      return new Promise<string>(resolve => {
        const fr = new FileReader()
        fr.onload = () => resolve(String(fr.result))
        fr.readAsText(this)
      })
    }

    it('posts the PNGs, downloads with the server file name and shows the Drive link', async () => {
      const store = useSesionStore()
      api.mockResolvedValueOnce(sesion(1))
      await store.load(1)
      raw.mockResolvedValueOnce(respuesta({ 'Content-Disposition': 'attachment; filename=elqui_pleito_2026-03-01.xlsx', 'X-Drive-Link': 'https://drive.google.com/file/d/x/view' }))
      const p = store.exportar(async () => ({ diagrama: new Blob(['png']) }))
      expect(store.busy).toBe('exportar')
      await p
      const [url, opts] = raw.mock.calls[0]!
      expect(url).toBe('/v2/sesiones/1/exportar')
      expect((opts.body as FormData).get('diagrama')).toBeInstanceOf(Blob)
      expect(descargar).toHaveBeenCalledWith(expect.any(Blob), 'elqui_pleito_2026-03-01.xlsx')
      expect(toasts.at(-1)?.link?.href).toBe('https://drive.google.com/file/d/x/view')
      expect(store.busy).toBe('')
    })

    it('exports without images when the capture fails, and ignores a non-Drive link', async () => {
      const store = useSesionStore()
      api.mockResolvedValueOnce(sesion(1))
      await store.load(1)
      raw.mockResolvedValueOnce(respuesta({ 'X-Drive-Link': 'https://evil.example/x' }))
      await store.exportar(async () => { throw new Error('el diagrama tardó demasiado en cargar') })
      expect([...(raw.mock.calls[0]![1].body as FormData).keys()]).toEqual([])
      expect(descargar).toHaveBeenCalledWith(expect.any(Blob), 'balance_1.xlsx')
      expect(toasts[0]?.message).toContain('el diagrama tardó demasiado')
      expect(toasts.at(-1)).toMatchObject({ tone: 'ok', link: undefined })
    })

    it('shows the server message of a failed export (JSON read as a Blob)', async () => {
      const store = useSesionStore()
      api.mockResolvedValueOnce(sesion(1))
      await store.load(1)
      const { FetchError } = await import('ofetch')
      const err = new FetchError('500')
      err.data = new Blob([JSON.stringify({ message: 'No se pudo generar el Excel con la plantilla del proceso.' })])
      raw.mockRejectedValueOnce(err)
      await store.exportar()
      expect(descargar).not.toHaveBeenCalled()
      expect(toasts.at(-1)).toMatchObject({ tone: 'danger', message: 'No se pudo generar el Excel con la plantilla del proceso.' })
    })

    it('does nothing while there are unsaved edits', async () => {
      const store = useSesionStore()
      api.mockResolvedValueOnce(sesion(1))
      await store.load(1)
      store.dirty = true
      await store.exportar()
      expect(raw).not.toHaveBeenCalled()
    })
  })
})
