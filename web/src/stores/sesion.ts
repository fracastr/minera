import { defineStore } from 'pinia'
import { computed, reactive, ref } from 'vue'
import { toEdits, type Sesion } from '@/domain/types'
import { api, errorMessage } from '@/lib/api'
import { ask, notify } from '@/lib/feedback'

export type Busy = 'cargar' | 'correr' | 'guardar' | 'restaurar' | 'exportar'

/** The open balance workspace: one session, its unsaved edits and the actions on it. */
export const useSesionStore = defineStore('sesion', () => {
  const sesion = ref<Sesion | null>(null)
  const dirty = ref(false)
  const loadError = ref('')
  /** Node whose streams are highlighted in the flows grid. */
  const nodoSel = ref<number | null>(null)
  /** Saved balance reopened: the user already chose to overwrite it in this visit (v1 asks once). */
  const sobrescribir = ref(false)

  /** Operations in flight; the grids are read-only while any is running. */
  const ops = reactive(new Set<Busy>())
  const busy = computed<Busy | ''>(() => [...ops][0] ?? '')
  /** Bumped on every edit, so a response can tell whether the user typed while it was in flight. */
  let editVersion = 0
  /** Bumped by every request that replaces `sesion`; only the latest one may assign its response. */
  let epoch = 0

  const estado = computed(() => (sesion.value?.balance ? 'Guardado' : sesion.value?.corrida ? 'Corrido' : 'Importado'))

  function reset() {
    epoch++
    sesion.value = null
    dirty.value = false
    loadError.value = ''
    nodoSel.value = null
    sobrescribir.value = false
    writable = null
    ops.clear()
  }

  async function load(id: number) {
    reset()
    const mine = epoch
    ops.add('cargar')
    try {
      const res = await api<Sesion>(`/v2/sesiones/${id}`)
      if (mine === epoch) sesion.value = res
    } catch (e) {
      if (mine === epoch) loadError.value = errorMessage(e, 'No se pudo abrir la sesión.')
    } finally {
      if (mine === epoch) ops.delete('cargar')
    }
  }

  /** Runs one request against the open session; its result is applied only if the session is still the same. */
  async function run<T>(kind: Busy, fn: (id: number) => Promise<T>, fallback: string): Promise<{ value: T; fresh: boolean } | undefined> {
    const s = sesion.value
    if (!s) return
    const mine = ++epoch
    const edits = editVersion
    ops.add(kind)
    try {
      const value = await fn(s.id)
      if (sesion.value !== s) return // the user left or opened another session meanwhile
      return { value, fresh: mine === epoch && edits === editVersion }
    } catch (e) {
      if (sesion.value === s) notify('danger', errorMessage(e, fallback))
    } finally {
      ops.delete(kind)
    }
  }

  /** Called by the grids after every change. */
  function markEdited() {
    editVersion++
  }

  // One question at a time: concurrent edits share the same answer.
  let writable: Promise<boolean> | null = null

  /**
   * Before the first change to a reopened saved balance, ask whether to fork it or overwrite it.
   * Forking clones the session server-side and keeps the in-memory edits on the clone.
   * Resolves false when the user cancels. The page updates the URL when the id changes.
   */
  function ensureWritable(): Promise<boolean> {
    const s = sesion.value
    if (!s?.balance || sobrescribir.value) return Promise.resolve(!!s)
    writable ??= (async () => {
      const choice = await ask(
        `«${s.balance!.nombre}» ya está guardado`,
        'Puedes trabajar sobre una copia, que se guardará como un balance nuevo, o modificar y sobrescribir el balance guardado.',
        [
          { value: 'sobrescribir', label: 'Sobrescribir', tone: 'quiet' },
          { value: 'nuevo', label: 'Guardar como nuevo', tone: 'primary' },
        ],
      )
      if (choice === 'sobrescribir') sobrescribir.value = true
      if (choice !== 'nuevo') return choice === 'sobrescribir'
      try {
        const { id } = await api<{ id: number }>(`/v2/sesiones/${s.id}/clonar`, { method: 'POST' })
        if (sesion.value !== s) return false
        s.id = id
        s.balance = null
        return true
      } catch (e) {
        notify('danger', errorMessage(e, 'No se pudo crear la copia.'))
        return false
      }
    })().finally(() => (writable = null))
    return writable
  }

  /** A grid changed a value: on a reopened saved balance, ask first; cancelling reloads the stored data. */
  async function edited() {
    markEdited()
    const s = sesion.value
    if (!s) return
    if (await ensureWritable()) dirty.value = true
    else if (sesion.value === s) load(s.id)
  }

  function applyResult(res: { value: Sesion; fresh: boolean }, okMessage: string) {
    if (res.fresh) {
      sesion.value = res.value
      dirty.value = false
      notify('ok', okMessage)
    } else {
      // The server has the result, but replacing the grid now would drop what the user typed meanwhile.
      dirty.value = true
      notify('danger', `${okMessage.replace(/\.$/, '')}, pero con los datos anteriores a tus últimas ediciones. Vuelve a correr para incluirlas.`)
    }
  }

  async function correr() {
    if (!sesion.value || !(await ensureWritable())) return
    const body = toEdits(sesion.value)
    const res = await run('correr', id => api<Sesion>(`/v2/sesiones/${id}/correr`, { method: 'POST', body }), 'No se pudo correr el balance.')
    if (res) applyResult(res, 'Balance corrido. Los valores reconciliados están actualizados.')
  }

  /** Always saves the grid first (PUT datos), then the balance record. Resolves the balance id. */
  async function guardar(form: { nombre: string; tipo: string; periodo_inicio: string; periodo_fin: string }) {
    const s = sesion.value
    if (!s) return
    const body = toEdits(s)
    const res = await run('guardar', async id => {
      await api(`/v2/sesiones/${id}/datos`, { method: 'PUT', body })
      const { balance_id } = await api<{ balance_id: number }>(`/v2/sesiones/${id}/guardar`, {
        method: 'POST',
        body: { ...form, sobrescribir: !!s.balance },
      })
      return { balance_id, sesion: await api<Sesion>(`/v2/sesiones/${id}`) }
    }, 'No se pudo guardar el balance.')
    if (!res) return
    sobrescribir.value = true
    if (res.fresh) {
      sesion.value = res.value.sesion
      dirty.value = false
      notify('ok', `Balance «${form.nombre}» guardado.`)
    } else {
      s.balance = res.value.sesion.balance // keep the newer edits, but the session now belongs to the balance
      dirty.value = true
      notify('danger', `Balance «${form.nombre}» guardado sin tus últimas ediciones. Vuelve a guardar para incluirlas.`)
    }
    return res.value.balance_id
  }

  async function restaurar(corridaId: number) {
    if (!sesion.value) return false
    if (dirty.value) {
      const ok = await ask('Hay cambios sin guardar', 'Restaurar una versión reemplaza los datos y descarta las ediciones que no has corrido ni guardado.', [
        { value: 'si', label: 'Descartar y restaurar', tone: 'danger' },
      ])
      if (!ok) return false
    }
    if (!(await ensureWritable())) return false
    const res = await run('restaurar', id => api<Sesion>(`/v2/sesiones/${id}/corridas/${corridaId}/restaurar`, { method: 'POST' }), 'No se pudo restaurar la versión.')
    if (!res) return false
    sesion.value = res.value // a restore deliberately replaces everything, edits included
    dirty.value = false
    notify('ok', 'Versión restaurada.')
    return true
  }

  async function exportar() {
    const s = sesion.value
    if (!s) return
    // Open the tab now (a tab opened after an await is blocked as a popup), then point it at Drive.
    const tab = window.open('', '_blank')
    if (tab) tab.opener = null
    const res = await run('exportar', id => api(`/balances/getExcel/${id}/${s.proceso.id}`, { retry: 0, responseType: 'text' }), 'No se pudo generar el Excel.')
    const url = typeof res?.value === 'string' ? res.value.trim() : ''
    const safe = /^https:\/\/(drive|docs)\.google\.com\//.test(url)
    if (tab && safe) tab.location.href = url
    else tab?.close()
    if (!res) return
    if (!safe) notify('danger', 'El Excel se generó, pero el enlace de Drive no es válido.')
    else if (!tab) notify('ok', 'El Excel está listo. El navegador bloqueó la pestaña nueva:', { href: url, label: 'Abrir en Drive' })
  }

  return { sesion, dirty, busy, loadError, nodoSel, estado, reset, load, ensureWritable, edited, correr, guardar, restaurar, exportar }
})
