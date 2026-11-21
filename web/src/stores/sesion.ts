import { FetchError } from 'ofetch'
import { defineStore } from 'pinia'
import { computed, reactive, ref, shallowRef } from 'vue'
import type { MensajeChat, RespuestaAsistente } from '@/domain/asistente'
import { claveSospechoso, flujoDe, leyIdx, sugerencia, type Accion, type Diagnostico, type VariableDiag } from '@/domain/diagnostico'
import { toEdits, type Sesion } from '@/domain/types'
import { descargar } from '@/composables/useDiagramImage'
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
  /** Flow to bring into view in the flows grid ("Editar en Datos" from the diagram). */
  const flujoFoco = ref<number | null>(null)
  /** Gross-error diagnosis of the stored data, and the session version (updated_at) it was computed for. */
  const diag = shallowRef<Diagnostico | null>(null)
  const diagVersion = ref<string | null>(null)
  const diagCargando = ref(false)
  const diagError = ref('')
  /** Suspects the user dismissed in this visit (client only), by claveSospechoso. */
  const descartados = reactive(new Set<string>())
  /** Diagram overlay: mark the suspect streams; `flujoPulso` pulses one ("Ver en diagrama"). */
  const overlaySospechosos = ref(false)
  const flujoPulso = ref<number | null>(null)
  /** Assistant conversation (S4): in memory only, per session; reset() clears it. */
  const chat = ref<MensajeChat[]>([])
  const chatCargando = ref(false)
  const chatError = ref('')
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
    flujoFoco.value = null
    sobrescribir.value = false
    diag.value = null
    diagVersion.value = null
    diagError.value = ''
    diagCargando.value = false
    descartados.clear()
    overlaySospechosos.value = false
    flujoPulso.value = null
    chat.value = []
    chatCargando.value = false
    chatError.value = ''
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

  /**
   * Excel of the stored session (POST /v2/sesiones/{id}/exportar): `imagenes` supplies the PNGs for the extra sheets
   * (a capture failure exports without them). Downloads the file and shows the Drive copy when the server made one.
   */
  async function exportar(imagenes: () => Promise<Record<string, Blob>> = async () => ({})) {
    const s = sesion.value
    if (!s || dirty.value || ops.has('exportar')) return
    ops.add('exportar')
    try {
      let pngs: Record<string, Blob> = {}
      try {
        pngs = await imagenes()
      } catch (e) {
        notify('danger', `El Excel se exporta sin el diagrama ni los gráficos: ${e instanceof Error ? e.message : 'no se pudieron capturar'}.`)
      }
      const body = new FormData()
      for (const [campo, png] of Object.entries(pngs)) body.append(campo, png, `${campo}.png`)
      const res = await api.raw<Blob, 'blob'>(`/v2/sesiones/${s.id}/exportar`, { method: 'POST', body, responseType: 'blob', retry: 0 })
      if (sesion.value !== s) return
      const nombre = /filename="?([^";]+)"?/.exec(res.headers.get('Content-Disposition') ?? '')?.[1] ?? `balance_${s.id}.xlsx`
      descargar(res._data!, nombre)
      const drive = res.headers.get('X-Drive-Link') ?? ''
      if (/^https:\/\/(drive|docs)\.google\.com\//.test(drive)) notify('ok', 'Excel descargado. También quedó en Drive:', { href: drive, label: 'Abrir en Drive' })
      else notify('ok', 'Excel descargado.')
    } catch (e) {
      // The body was read as a Blob: decode the JSON error so the server's message is shown.
      if (e instanceof FetchError && e.data instanceof Blob) e.data = await e.data.text().then(JSON.parse).catch(() => null)
      if (sesion.value === s) notify('danger', errorMessage(e, 'No se pudo generar el Excel.'))
    } finally {
      ops.delete('exportar')
    }
  }

  let diagSeq = 0
  /** Loads the diagnosis unless the one in hand is for this version of the session. */
  async function cargarDiagnostico() {
    const s = sesion.value
    if (!s || (diag.value && diagVersion.value === s.updated_at)) return
    const mine = ++diagSeq
    const version = s.updated_at
    diagCargando.value = true
    diagError.value = ''
    try {
      const res = await api<Diagnostico>(`/v2/sesiones/${s.id}/diagnostico`)
      if (mine !== diagSeq || sesion.value !== s) return
      diag.value = res
      diagVersion.value = version
    } catch (e) {
      if (mine !== diagSeq || sesion.value !== s) return
      diagError.value = e instanceof FetchError && e.status === 503
        ? 'Diagnóstico no disponible (servicio en actualización).'
        : errorMessage(e, 'No se pudo calcular el diagnóstico.')
    } finally {
      if (mine === diagSeq) diagCargando.value = false
    }
  }

  /** Session rows of the suspects still shown (and of their equivalents), for the diagram overlay. */
  const flujosSospechosos = computed(() => {
    const s = sesion.value
    const out = new Set<number>()
    for (const x of diag.value?.diagnostico.sospechosos ?? []) {
      if (!s || descartados.has(claveSospechoso(x))) continue
      for (const e of [x, ...x.equivalentes]) {
        const f = flujoDe(s.flujos, e.flujo, e.tag)
        if (f) out.add(f.idx)
      }
    }
    return out
  })

  /**
   * "Aplicar sugerencia" (and the S4 assistant's proposals): writes the suggested value or widened tolerance into
   * the session like a grid edit (same ownership question, dirty flag and edit version). It does not run.
   */
  async function aplicarSugerencia(flujo: number, variable: VariableDiag, accion: Accion, sugerido: number): Promise<boolean> {
    const s = sesion.value
    const f = s?.flujos[flujo]
    if (!s || !f || busy.value || !Number.isFinite(sugerido) || !(await ensureWritable())) return false
    if (sesion.value !== s) return false
    const x = leyIdx(variable) < 0 ? f.tms : f.leyes[leyIdx(variable)]
    if (!x) return false
    const sg = sugerencia(f, variable, accion, sugerido)
    if (sg.campo === 'medido') x.medido = sg.valor
    else x.tol_inf = x.tol_sup = sg.valor
    markEdited()
    dirty.value = true
    notify('ok', sg.campo === 'medido' ? 'Valor aplicado; corre el balance para ver el efecto.' : 'Tolerancia ampliada; corre el balance para ver el efecto.')
    return true
  }

  /** Sends a question (or, without one, retries the last) with the conversation so far. */
  async function preguntarAsistente(texto?: string) {
    const s = sesion.value
    if (!s || chatCargando.value) return
    if (texto) chat.value.push({ rol: 'usuario', texto })
    // The API takes the last 20 messages, starting with the user's, each at most 4000 characters.
    const mensajes = chat.value.slice(-20).map(({ rol, texto }) => ({ rol, texto: texto.slice(0, 4000) }))
    while (mensajes.length && mensajes[0]!.rol !== 'usuario') mensajes.shift()
    if (mensajes.at(-1)?.rol !== 'usuario') return
    chatCargando.value = true
    chatError.value = ''
    try {
      const res = await api<RespuestaAsistente>(`/v2/sesiones/${s.id}/asistente`, { method: 'POST', body: { mensajes }, retry: 0 })
      if (sesion.value === s) chat.value.push({ rol: 'asistente', texto: res.texto, propuestas: res.propuestas })
    } catch (e) {
      if (sesion.value === s) chatError.value = errorMessage(e, 'El asistente no respondió. Intenta nuevamente.')
    } finally {
      if (sesion.value === s) chatCargando.value = false
    }
  }

  return { chat, chatCargando, chatError, preguntarAsistente, diag, diagCargando, diagError, descartados, overlaySospechosos, flujoPulso, flujosSospechosos, cargarDiagnostico, aplicarSugerencia,
    sesion, dirty, busy, loadError, nodoSel, flujoFoco, estado, reset, load, ensureWritable, edited, correr, guardar, restaurar, exportar }
})
