import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { toEdits, type Sesion } from '@/domain/types'
import { api, errorMessage } from '@/lib/api'
import { ask, notify } from '@/lib/feedback'

export type Busy = '' | 'cargar' | 'correr' | 'guardar' | 'restaurar' | 'exportar'

/** The open balance workspace: one session, its unsaved edits and the actions on it. */
export const useSesionStore = defineStore('sesion', () => {
  const sesion = ref<Sesion | null>(null)
  const dirty = ref(false)
  const busy = ref<Busy>('')
  const loadError = ref('')
  /** Node whose streams are highlighted in the flows grid. */
  const nodoSel = ref<number | null>(null)
  /** Flow to bring into view in the flows grid ("Editar en Datos" from the diagram). */
  const flujoFoco = ref<number | null>(null)
  /** Saved balance reopened: the user already chose to overwrite it in this visit (v1 asks once). */
  const sobrescribir = ref(false)

  const estado = computed(() => (sesion.value?.balance ? 'Guardado' : sesion.value?.corrida ? 'Corrido' : 'Importado'))

  async function load(id: number) {
    busy.value = 'cargar'
    loadError.value = ''
    try {
      sesion.value = await api<Sesion>(`/v2/sesiones/${id}`)
      dirty.value = false
      nodoSel.value = null
      flujoFoco.value = null
      sobrescribir.value = false
    } catch (e) {
      sesion.value = null
      loadError.value = errorMessage(e, 'No se pudo abrir la sesión.')
    } finally {
      busy.value = ''
    }
  }

  /**
   * Before the first change to a reopened saved balance, ask whether to fork it or overwrite it.
   * Forking clones the session server-side and keeps the in-memory edits on the clone.
   * Resolves false when the user cancels. The caller updates the URL when the id changes.
   */
  async function ensureWritable(): Promise<boolean> {
    const s = sesion.value
    if (!s?.balance || sobrescribir.value) return true
    const choice = await ask(
      `«${s.balance.nombre}» ya está guardado`,
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
      s.id = id
      s.balance = null
      return true
    } catch (e) {
      notify('danger', errorMessage(e, 'No se pudo crear la copia.'))
      return false
    }
  }

  async function run<T>(kind: Busy, fn: () => Promise<T>, fallback: string): Promise<T | undefined> {
    busy.value = kind
    try {
      return await fn()
    } catch (e) {
      notify('danger', errorMessage(e, fallback))
    } finally {
      busy.value = ''
    }
  }

  async function correr() {
    if (!sesion.value || !(await ensureWritable())) return
    const s = sesion.value
    const res = await run('correr', () => api<Sesion>(`/v2/sesiones/${s.id}/correr`, { method: 'POST', body: toEdits(s) }), 'No se pudo correr el balance.')
    if (!res) return
    sesion.value = res
    dirty.value = false
    notify('ok', 'Balance corrido. Los valores reconciliados están actualizados.')
  }

  /** Edits first (PUT datos), then the balance record. Resolves the balance id. */
  async function guardar(body: { nombre: string; tipo: string; periodo_inicio: string; periodo_fin: string }) {
    const s = sesion.value
    if (!s) return
    const balanceId = await run('guardar', async () => {
      if (dirty.value) await api(`/v2/sesiones/${s.id}/datos`, { method: 'PUT', body: toEdits(s) })
      dirty.value = false
      return (await api<{ balance_id: number }>(`/v2/sesiones/${s.id}/guardar`, {
        method: 'POST',
        body: { ...body, sobrescribir: !!s.balance },
      })).balance_id
    }, 'No se pudo guardar el balance.')
    if (!balanceId) return
    sesion.value = await api<Sesion>(`/v2/sesiones/${s.id}`)
    sobrescribir.value = true
    notify('ok', `Balance «${body.nombre}» guardado.`)
    return balanceId
  }

  async function restaurar(corridaId: number) {
    if (!sesion.value || !(await ensureWritable())) return false
    const s = sesion.value
    const res = await run('restaurar', () => api<Sesion>(`/v2/sesiones/${s.id}/corridas/${corridaId}/restaurar`, { method: 'POST' }), 'No se pudo restaurar la versión.')
    if (!res) return false
    sesion.value = res
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
    const url = await run('exportar', () => api(`/balances/getExcel/${s.id}/${s.proceso.id}`, { retry: 0, responseType: 'text' }), 'No se pudo generar el Excel.')
    const safe = typeof url === 'string' && /^https:\/\/(drive|docs)\.google\.com\//.test(url.trim())
    if (tab && safe) tab.location.href = url.trim()
    else tab?.close()
    if (url !== undefined && !safe) notify('danger', 'El Excel se generó, pero el enlace de Drive no es válido.')
  }

  return { sesion, dirty, busy, loadError, nodoSel, flujoFoco, estado, load, ensureWritable, correr, guardar, restaurar, exportar }
})
