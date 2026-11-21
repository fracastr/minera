<script setup lang="ts">
import { FileDown, History, LoaderCircle, Play, Save, TriangleAlert } from '@lucide/vue'
import { TabsContent, TabsIndicator, TabsList, TabsRoot, TabsTrigger } from 'reka-ui'
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import Notice from '@/components/Notice.vue'
import DatosTab from '@/components/sesion/DatosTab.vue'
import GuardarDialog from '@/components/sesion/GuardarDialog.vue'
import HistorialTab from '@/components/sesion/HistorialTab.vue'
import { can } from '@/lib/abilities'
import { ask } from '@/lib/feedback'
import { hasta } from '@/lib/hasta'
import { periodo, valleColor } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { useSesionStore } from '@/stores/sesion'

// The flowsheet (Vue Flow + ELK) loads only when its tab opens.
const DiagramaTab = defineAsyncComponent(() => import('@/components/diagrama/DiagramaTab.vue'))
// ECharts loads only with the diagnosis.
const DiagnosticoTab = defineAsyncComponent(() => import('@/components/diagnostico/DiagnosticoTab.vue'))

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useSesionStore()

const canWrite = computed(() => can(auth.user?.ability, 'update', 'Balances'))
const tab = ref('datos')
const guardarOpen = ref(false)
const s = computed(() => store.sesion)

// A run (or saved) session opens on the diagram; one only imported opens on its data.
const tabInicial = () => (store.sesion?.corrida || store.sesion?.balance ? 'diagrama' : 'datos')

// Load on entry and when the URL changes, but not when a "guardar como nuevo" clone just took over the URL.
watch(() => Number(route.params.id), id => {
  if (!id || id === store.sesion?.id) return
  // A tab the user picked while the session was loading wins over the default.
  const antes = tab.value
  store.load(id).then(() => {
    if (tab.value === antes) tab.value = tabInicial()
  })
}, { immediate: true })

async function correr() {
  await store.correr()
  // From Datos the result is best seen on the diagram; Diagnóstico stays put to show the re-test.
  if (store.sesion?.corrida && !store.dirty && tab.value === 'datos') tab.value = 'diagrama'
}

function editarEnDatos(flujo: number) {
  store.flujoFoco = flujo
  tab.value = 'datos'
}
watch(() => store.sesion?.id, id => {
  if (id && id !== Number(route.params.id)) router.replace({ name: 'balance-sesion', params: { id } })
})


// ── Excel export ────────────────────────────────────────────────────────────
// A run session sends the diagram and, when the diagnosis is available, its charts as PNGs. They are drawn by
// copies of the tabs mounted off-screen for the capture, so the export works from any tab and always shows the
// default view (current theme included).
const captura = ref(false)
const capDiagrama = ref<{ png: () => Promise<Blob> }>()
const capDiagnostico = ref<{ pngs: () => Promise<Record<string, Blob>> }>()
async function imagenes(): Promise<Record<string, Blob>> {
  const sv = store.sesion
  if (!sv || !(sv.corrida || sv.balance)) return {}
  await store.cargarDiagnostico()
  const conGraficos = !!store.diag
  captura.value = true
  try {
    await hasta(() => capDiagrama.value && (!conGraficos || capDiagnostico.value), 30_000, 'el diagrama tardó demasiado en cargar')
    const out: Record<string, Blob> = { diagrama: await capDiagrama.value!.png() }
    if (conGraficos) Object.assign(out, await capDiagnostico.value!.pngs())
    return out
  } finally {
    captura.value = false
  }
}
const exportar = () => store.exportar(imagenes)

async function abrirGuardar() {
  if (await store.ensureWritable()) guardarOpen.value = true
}
async function guardar(body: Parameters<typeof store.guardar>[0]) {
  if (await store.guardar(body)) guardarOpen.value = false
}

// Unsaved edits: confirm before leaving the page, opening another session or closing the tab.
const confirmDiscard = async () =>
  !!(await ask('Hay cambios sin guardar', 'Si sales ahora se pierden las ediciones que no has corrido ni guardado.', [
    { value: 'salir', label: 'Salir sin guardar', tone: 'danger' },
  ]))
onBeforeRouteLeave(() => (store.dirty ? confirmDiscard() : true))
// Another session by URL (back/forward, a link) asks too; a clone taking over this session's URL does not.
onBeforeRouteUpdate(to => (store.dirty && Number(to.params.id) !== store.sesion?.id ? confirmDiscard() : true))
const beforeUnload = (e: BeforeUnloadEvent) => store.dirty && e.preventDefault()
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnload)
  store.reset() // nothing of this visit survives: coming back always reloads from the server
})

const estadoStyle = computed(() => ({
  Importado: 'bg-surface-2 text-ink-muted',
  Corrido: 'bg-primary-soft text-primary',
  Guardado: 'bg-ok-soft text-ok',
})[store.estado])

const tabs = [
  { value: 'diagrama', label: 'Diagrama' },
  { value: 'datos', label: 'Datos' },
  { value: 'diagnostico', label: 'Diagnóstico' },
  { value: 'historial', label: 'Historial' },
]
</script>

<template>
  <div v-if="!s && store.busy === 'cargar'" class="grid min-h-80 place-items-center text-ink-muted" role="status">
    <span class="flex items-center gap-2"><LoaderCircle class="size-4 animate-spin" aria-hidden="true" /> Abriendo sesión…</span>
  </div>
  <div v-else-if="!s" class="mx-auto max-w-xl">
    <Notice tone="danger">{{ store.loadError || 'No se pudo abrir la sesión.' }}</Notice>
    <RouterLink :to="{ name: 'balances' }" class="btn btn-quiet mt-4">Volver al listado</RouterLink>
  </div>

  <TabsRoot v-else v-model="tab" class="mx-auto max-w-[96rem]">
    <header class="z-20 md:sticky md:top-14 -mx-4 -mt-6 border-b border-line bg-surface/95 px-4 pt-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:-mt-8 lg:px-8">
      <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
          <p class="flex items-center gap-2 text-[0.8125rem] text-ink-muted">
            <span class="size-2 rounded-full" :style="{ background: valleColor(s.proceso.valle.nombre) }" aria-hidden="true" />
            {{ s.proceso.valle.nombre }} · {{ s.proceso.nombre }}
          </p>
          <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
            <h1 class="truncate font-display text-lg font-semibold">{{ s.balance?.nombre ?? 'Sin guardar' }}</h1>
            <span class="rounded px-1.5 py-0.5 text-[0.6875rem] font-semibold" :class="estadoStyle">{{ store.estado }}</span>
            <span
              v-if="s.solver?.ok === false" class="inline-flex items-center gap-1 rounded bg-danger-soft px-1.5 py-0.5 text-[0.6875rem] font-semibold text-danger"
              role="status" :title="`El solver no convergió${s.solver.mensaje ? `: ${s.solver.mensaje}` : ''}. Los valores reconciliados no son confiables.`"
            >
              <TriangleAlert class="size-3.5" aria-hidden="true" /> Solver sin converger: resultados no confiables
            </span>
            <span v-if="store.dirty" class="inline-flex items-center gap-1.5 text-xs font-medium text-warn" role="status">
              <span class="size-1.5 rounded-full bg-warn" aria-hidden="true" /> Cambios sin guardar
            </span>
          </div>
          <p v-if="s.balance?.periodo_inicio" class="text-xs text-ink-muted">{{ periodo(s.balance.periodo_inicio, s.balance.periodo_fin) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn btn-quiet h-9 px-3" @click="tab = 'historial'">
            <History class="size-4" aria-hidden="true" /> Historial
          </button>
          <template v-if="canWrite">
            <button
              type="button" class="btn btn-quiet h-9 px-3"
              :disabled="!!store.busy || store.dirty"
              :aria-busy="store.busy === 'exportar'"
              :title="store.dirty ? 'Corre o guarda los cambios antes de exportar' : 'Descarga el Excel con el diagrama y los gráficos'"
              @click="exportar"
            >
              <LoaderCircle v-if="store.busy === 'exportar'" class="size-4 animate-spin" aria-hidden="true" />
              <FileDown v-else class="size-4" aria-hidden="true" /> {{ store.busy === 'exportar' ? 'Exportando…' : 'Exportar' }}
            </button>
            <button type="button" class="btn btn-quiet h-9 px-3" :disabled="!!store.busy" @click="abrirGuardar">
              <Save class="size-4" aria-hidden="true" /> Guardar
            </button>
            <button type="button" class="btn btn-primary h-9 px-4" :disabled="!!store.busy" @click="correr">
              <LoaderCircle v-if="store.busy === 'correr'" class="size-4 animate-spin" aria-hidden="true" />
              <Play v-else class="size-4" aria-hidden="true" />
              {{ store.busy === 'correr' ? 'Corriendo…' : 'Correr' }}
            </button>
          </template>
        </div>
      </div>
      <TabsList aria-label="Vistas del balance" class="relative mt-3 -mb-px flex gap-1 overflow-x-auto">
        <TabsTrigger
          v-for="t in tabs" :key="t.value" :value="t.value"
          class="h-10 shrink-0 px-3 text-[0.8125rem] font-medium text-ink-muted hover:text-ink data-[state=active]:text-ink"
        >
          {{ t.label }}
        </TabsTrigger>
        <TabsIndicator class="absolute bottom-0 left-0 h-0.5 w-(--reka-tabs-indicator-size) translate-x-(--reka-tabs-indicator-position) rounded-full bg-primary transition-[width,translate] duration-200" />
      </TabsList>
    </header>

    <div class="pt-6" :aria-busy="store.busy === 'correr'">
      <TabsContent value="diagrama">
        <DiagramaTab :sesion="s" :editable="canWrite" @editar="editarEnDatos" />
      </TabsContent>
      <TabsContent value="datos">
        <DatosTab v-model:nodo="store.nodoSel" :sesion="s" :editable="canWrite" :locked="!!store.busy" @edit="store.edited()" />
      </TabsContent>
      <TabsContent value="diagnostico">
        <DiagnosticoTab :sesion="s" :editable="canWrite" @ver="tab = 'diagrama'" />
      </TabsContent>
      <TabsContent value="historial">
        <HistorialTab :sesion-id="s.id" :version="s.updated_at" :editable="canWrite" />
      </TabsContent>
    </div>

    <!-- Off-screen copies drawn only while the export captures them (laid out, so measurable; hidden from AT and input). -->
    <div v-if="captura" class="pointer-events-none fixed top-0 -left-[20000px] w-[1400px]" aria-hidden="true" inert>
      <DiagramaTab ref="capDiagrama" :sesion="s" :editable="false" />
      <DiagnosticoTab v-if="store.diag" ref="capDiagnostico" :sesion="s" :editable="false" />
    </div>

    <GuardarDialog v-model:open="guardarOpen" :balance="s.balance" :busy="store.busy === 'guardar'" @guardar="guardar" />
  </TabsRoot>
</template>
