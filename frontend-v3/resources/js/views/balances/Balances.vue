<script setup>
// Balance workspace (port of resources/js/src/views/balances/Balances.vue). Embedded by BalanceFormWizard,
// which relies on the exposed contract at the bottom.
import { AgGridVue } from 'ag-grid-vue3'
import { AllCommunityModule, ModuleRegistry, themeQuartz } from 'ag-grid-community'
import { useTheme } from 'vuetify'
import BalanceSankey from './BalanceSankey.vue'
import { buildFlowGraph } from './flowGraph'
import { computeQuality } from './reconciliationQuality'

const props = defineProps({
  proceso: { type: Number, default: null },
  show_tables: { type: Boolean, default: false },
  sourceBalanceId: { type: Number, default: null },
  editMode: { type: String, default: null },
})

const emit = defineEmits(['edit-mode-chosen'])

ModuleRegistry.registerModules([AllCommunityModule])

const { t } = useI18n()
const notify = useNotify()
const vuetifyTheme = useTheme()
const isDark = computed(() => vuetifyTheme.global.current.value.dark)

// --- Formatters (same output as the Vue 2 app) ---
const decimalFormatter = params => parseFloat(params.value).toFixed(2)
const intFormatter = params => parseInt(String(params.value).replace(/,/g, ''), 10)
const intFormatter2 = params => parseInt(params.value, 10)

function nodosCellStyle(params) {
  if (!isNaN(params.value) && Math.abs(parseFloat(params.value)) > 0.001)
    return { backgroundColor: 'rgba(234, 84, 85, 0.16)', color: '#ea5455', fontWeight: 600 }

  return null
}

// --- State ---
// shallowRef: the grids own these rows (cell edits mutate them in place); see onCellValueChanged.
const balances_table = shallowRef([])
const restricciones_table = shallowRef([])
const balance_nodos = shallowRef([])
const inventarios_data = shallowRef([])
const balances_fields = shallowRef([])
const restricciones_fields = shallowRef([])
const balance_nodos_fields = shallowRef([])
const inventarios_fields = shallowRef([])
const datos_entrada = ref({})
const datos_entrada_id = ref(null)

const tablesVisible = ref(props.show_tables)
watch(() => props.show_tables, v => { tablesVisible.value = v })

const file = ref(null)
const formRef = ref()
const loaded = ref(false) // old correr_button / exportar_button
const isLoading = ref(false)
const yellow = ref([])
const green = ref([])

const gridBalances = ref(null)
const gridRestricciones = ref(null)
const restriccionesWrap = ref()

const modal = reactive({ open: false, kind: 0, title: '', fields: [], data: [] })
const sankeyOpen = ref(false)
const historyOpen = ref(false)
const corridas = ref([])
const corridasLoading = ref(false)
const calidadVerTodo = ref(false)
const calidadLimite = 8

const defaultColDef = {
  flex: 0,
  resizable: false,
  wrapText: false,
  autoHeight: false,
  wrapHeaderText: true,
  autoHeaderHeight: true,
}

// Compact Quartz tuned to the old Alpine look; colors follow the Vuetify light/dark theme.
const gridTheme = computed(() => themeQuartz.withParams({
  spacing: 4,
  fontFamily: 'inherit',
  fontSize: 12.5,
  headerFontSize: 11.5,
  headerFontWeight: 600,
  rowHeight: 28,
  headerHeight: 32,
  borderRadius: 6,
  wrapperBorderRadius: 6,
  accentColor: isDark.value ? '#3987e5' : '#2a78d6',
  browserColorScheme: isDark.value ? 'dark' : 'light',
  ...(isDark.value
    ? {
        foregroundColor: '#d0d2d6',
        backgroundColor: '#2f3349',
        headerBackgroundColor: '#3a3f5a',
        headerTextColor: '#d0d2d6',
        oddRowBackgroundColor: '#33384f',
        rowHoverColor: 'rgba(57, 135, 229, 0.12)',
        selectedRowBackgroundColor: 'rgba(57, 135, 229, 0.18)',
        borderColor: '#434968',
        rowBorder: { color: '#3a3f5a' },
        headerColumnResizeHandleColor: '#4a5072',
      }
    : {
        foregroundColor: '#5e5873',
        backgroundColor: '#fff',
        headerBackgroundColor: '#f3f2f7',
        headerTextColor: '#5e5873',
        oddRowBackgroundColor: '#fafafc',
        rowHoverColor: 'rgba(42, 120, 214, 0.07)',
        selectedRowBackgroundColor: 'rgba(42, 120, 214, 0.12)',
        borderColor: '#ebe9f1',
        rowBorder: { color: '#f0eff4' },
        headerColumnResizeHandleColor: '#e3e1ea',
      }),
}))

// --- Derived ---
const canWrite = computed(() => ['admin', 'operator'].includes(useCookie('userData').value?.role))

const calidad = computed(() => computeQuality({
  balancesTable: balances_table.value,
  restriccionesTable: restricciones_table.value,
  balanceNodos: balance_nodos.value,
  matriz: datos_entrada.value?.matriz || [],
}))

const flowGraph = computed(() => buildFlowGraph({
  matriz: datos_entrada.value?.matriz || [],
  balanceNodos: balance_nodos.value,
  balancesTable: balances_table.value,
  indicesStock: datos_entrada.value?.indices_stock || [],
}))

const calidadFilas = computed(() => calidadVerTodo.value ? calidad.value.flows : calidad.value.flows.slice(0, calidadLimite))

const calidadHeaders = computed(() => [
  { key: 'flujo', title: t('balances.quality.flow') },
  { key: 'variable', title: t('balances.quality.variable') },
  { key: 'ajustePct', title: t('balances.quality.adjustment'), align: 'end' },
  { key: 'tolerancia', title: t('balances.quality.tolerance') },
  { key: 'usoPct', title: t('balances.quality.usage'), minWidth: 140 },
  { key: 'estado', title: '' },
])

const corridasHeaders = computed(() => [
  { key: 'created_at', title: t('balances.history.date') },
  { key: 'tipo', title: t('balances.history.type') },
  { key: 'usuario_nombre', title: t('balances.history.user') },
  { key: 'acciones', title: '', align: 'end' },
])

function formatPct(value) {
  if (value === null || value === undefined)
    return '—'

  return `${value.toLocaleString('es-CL', { maximumFractionDigits: 2, minimumFractionDigits: 2 })}%`
}

const formatFecha = value => new Date(value).toLocaleString('es-CL', { dateStyle: 'medium', timeStyle: 'short' })

const estadoColor = estado => ({ 'ok': 'success', 'limite': 'warning', 'fuera': 'error', 'sin-tolerancia': 'secondary' })[estado]

const calidadKpis = computed(() => {
  const s = calidad.value.summary
  const max = s.mayorAjusteTms

  return [
    { label: t('balances.quality.flowsAtLimit'), value: s.enLimite, variant: s.enLimite ? 'warning' : 'success' },
    { label: t('balances.quality.flowsOutside'), value: s.fuera, variant: s.fuera ? 'danger' : 'success' },
    {
      label: t('balances.quality.maxTmsAdjustment'),
      value: max ? `${max.ajustePct > 0 ? '+' : ''}${formatPct(max.ajustePct)}` : '—',
      detail: max ? max.flujo : null,
      variant: 'neutral',
    },
    {
      label: t('balances.quality.nodesOpen'),
      value: `${s.nodosSinCerrar} / ${s.totalNodos}`,
      variant: s.nodosSinCerrar ? 'danger' : 'success',
    },
  ]
})

const nodosSinCerrarTexto = computed(() => calidad.value.nodes
  .filter(n => !n.cierra)
  .map(n => `${n.nodo} (${formatPct(n.cierrePct)})`)
  .join(', '))

// --- Contract ---
// Fills the tables from a /api/balances/* response: the raw body (ofetch) or an axios-like { data }.
function applyBalancePayload(response) {
  const data = response && 'balances_table' in response ? response : (response?.data || response)

  balances_fields.value = (data.balances_fields || []).map((col, i) => ({
    ...col,
    ...([2, 3].includes(i) && { valueFormatter: intFormatter }),
    ...([4, 5].includes(i) && { valueFormatter: decimalFormatter }),
  }))
  const nRestr = (data.restricciones_fields || []).length
  restricciones_fields.value = (data.restricciones_fields || []).map((col, i) => (
    i !== nRestr - 1 && i > 1 ? { ...col, valueFormatter: decimalFormatter } : { ...col }
  ))
  inventarios_fields.value = (data.inventarios_fields || []).map((col, i) => ({
    ...col,
    valueFormatter: [4, 8, 9].includes(i) ? decimalFormatter : (i === 0 ? undefined : intFormatter2),
  }))
  balance_nodos_fields.value = (data.balance_nodos_fields || []).map(col => ({ ...col, cellStyle: nodosCellStyle }))

  balances_table.value = data.balances_table || []
  restricciones_table.value = data.restricciones_table || []
  balance_nodos.value = data.balance_nodos || []
  inventarios_data.value = data.inventarios_data || []
  datos_entrada.value = data.data?.datos_entrada || {}
  datos_entrada_id.value = data.datos_entrada_id ?? null
  loaded.value = true
  tablesVisible.value = true
}

// Asks whether to overwrite or fork a reopened balance before modifying it; resolves true when it may proceed.
const editDecision = reactive({ open: false, resolve: null })

async function ensureEditDecision() {
  if (!props.sourceBalanceId || props.editMode)
    return true

  const saveAsNew = await new Promise(resolve => {
    editDecision.resolve = resolve
    editDecision.open = true
  })

  editDecision.open = false
  if (saveAsNew) {
    isLoading.value = true
    try {
      const res = await $api('/balances/clone_session', { method: 'POST', body: { datos_entrada_id: datos_entrada_id.value } })

      datos_entrada_id.value = res.datos_entrada_id
      emit('edit-mode-chosen', 'new')

      return true
    }
    catch (e) {
      console.error('clone_session', e)
      notify.error(t('balances.toast.cloneError'))

      return false
    }
    finally {
      isLoading.value = false
    }
  }

  emit('edit-mode-chosen', 'overwrite')

  return true
}

defineExpose({ applyBalancePayload, ensureEditDecision, datos_entrada, datos_entrada_id })

// --- Actions ---
async function onSubmit() {
  const { valid } = await formRef.value.validate()
  if (!valid)
    return
  if (!await ensureEditDecision())
    return

  isLoading.value = true

  const formData = new FormData()

  formData.append('file', Array.isArray(file.value) ? file.value[0] : file.value)
  formData.append('proceso_id', props.proceso)
  try {
    applyBalancePayload(await $api('/balances/import', { method: 'POST', body: formData }))
  }
  catch (e) {
    console.error('import', e)
    notify.error(t('balances.toast.importError'))
  }
  finally {
    isLoading.value = false
  }
}

function onReset() {
  formRef.value?.reset()
}

async function correrTables() {
  if (!await ensureEditDecision())
    return

  isLoading.value = true
  try {
    applyBalancePayload(await $api('/balances/correr_balance', {
      method: 'POST',
      body: {
        datos_entrada: datos_entrada.value,
        datos_entrada_id: datos_entrada_id.value,
        balances_table: balances_table.value,
        restricciones_table: restricciones_table.value,
        balance_nodos: balance_nodos.value,
        proceso_id: props.proceso,
        inventarios_table: inventarios_data.value,
      },
    }))
  }
  catch (e) {
    console.error('correr_balance', e)
    notify.error(t('balances.toast.runError'))
  }
  finally {
    isLoading.value = false
  }
}

async function exportarExcel() {
  isLoading.value = true
  try {
    // retry: 0 — ofetch retries failed GETs by default, which would rebuild the workbook twice.
    const url = await $api(`/balances/getExcel/${datos_entrada_id.value}/${props.proceso}`, { retry: 0 })

    window.open(url, '_blank')?.focus()
  }
  catch (e) {
    console.error('getExcel', e)
    notify.error(t('balances.toast.exportError'))
  }
  finally {
    isLoading.value = false
  }
}

async function cargarCorridas() {
  corridasLoading.value = true
  try {
    corridas.value = (await $api(`/balances/corridas/${datos_entrada_id.value}`)).corridas
  }
  catch (e) {
    corridas.value = []
    notify.error(t('balances.toast.historyError'))
  }
  finally {
    corridasLoading.value = false
  }
}

function abrirHistorial() {
  historyOpen.value = true
  cargarCorridas()
}

async function restaurarCorrida(corrida) {
  // A reopened saved balance must first choose overwrite vs. copy, like a run.
  if (!await ensureEditDecision())
    return

  isLoading.value = true
  try {
    applyBalancePayload(await $api(`/balances/corridas/${corrida.id}/restaurar`, {
      method: 'POST',
      body: { datos_entrada_id: datos_entrada_id.value },
    }))
    yellow.value = []
    green.value = []
    notify.success(t('balances.toast.restoreSuccess'))
    await cargarCorridas()
  }
  catch (e) {
    notify.error(t('balances.toast.restoreError'))
  }
  finally {
    isLoading.value = false
  }
}

function tblExpand(kind) {
  const sources = {
    1: ['balances.modals.balancesDetail', balances_fields, balances_table],
    2: ['balances.modals.restrictionsDetail', restricciones_fields, restricciones_table],
    3: ['balances.modals.nodeAdjustmentDetail', balance_nodos_fields, balance_nodos],
    4: ['balances.modals.inventoryVariationsDetail', inventarios_fields, inventarios_data],
  }
  const [titleKey, fields, data] = sources[kind]

  Object.assign(modal, { kind, title: t(titleKey), fields: fields.value, data: data.value, open: true })
}

// Yellow/green rows come from paint_tables (clicking a node); balances and restrictions share row order.
function getRowStyle(params) {
  if (green.value.includes(params.node.rowIndex))
    return { background: 'rgba(40, 199, 111, 0.24)' }
  if (yellow.value.includes(params.node.rowIndex))
    return { background: 'rgba(250, 178, 25, 0.28)' }

  return undefined
}

async function onNodoClicked(event) {
  try {
    const res = await $api('/balances/paint_tables', {
      method: 'POST',
      body: { datos_entrada_id: datos_entrada_id.value, rowIndex: event.rowIndex },
    })

    yellow.value = res.yellow
    green.value = res.green
    gridBalances.value?.redrawRows()
    gridRestricciones.value?.redrawRows()
  }
  catch (e) {
    console.error('paint_tables', e)
    notify.error(t('balances.toast.runError'))
  }
}

// Scrolling the balances table keeps the restrictions table on the same rows.
function onBalancesScroll(e) {
  if (e.direction !== 'vertical')
    return
  const viewport = restriccionesWrap.value?.querySelector('.ag-body-viewport')
  if (viewport)
    viewport.scrollTop = e.top
}

// Grid edits mutate the row objects in place; re-run the quality panel and Sankey on them.
function onCellValueChanged() {
  triggerRef(balances_table)
  triggerRef(restricciones_table)
}
</script>

<template>
  <div :class="['balances-workspace', { 'balances-workspace--dark': isDark }]">
    <VForm
      ref="formRef"
      @submit.prevent="onSubmit"
      @reset.prevent="onReset"
    >
      <VRow>
        <VCol
          cols="12"
          md="4"
          class="d-flex align-center"
        >
          <label
            for="h-archivo-carga"
            class="text-body-1"
          >{{ $t('balances.fileUpload.label') }}</label>
        </VCol>
        <VCol
          cols="12"
          md="8"
        >
          <VFileInput
            id="h-archivo-carga"
            v-model="file"
            :placeholder="$t('balances.fileUpload.placeholder')"
            :rules="[v => (Array.isArray(v) ? v.length > 0 : !!v) || $t('balances.fileUpload.placeholder')]"
            prepend-icon=""
            prepend-inner-icon="tabler-paperclip"
            density="compact"
          />
        </VCol>
        <VCol
          cols="12"
          md="8"
          offset-md="4"
          class="d-flex flex-wrap gap-2"
        >
          <VBtn type="submit">
            {{ $t('balances.buttons.load') }}
          </VBtn>
          <VBtn
            type="reset"
            color="secondary"
            variant="outlined"
          >
            {{ $t('balances.buttons.reset') }}
          </VBtn>
          <template v-if="loaded">
            <VBtn
              color="warning"
              @click="correrTables"
            >
              {{ $t('balances.buttons.run') }}
            </VBtn>
            <VBtn
              color="success"
              @click="exportarExcel"
            >
              {{ $t('balances.buttons.generateExcel') }}
            </VBtn>
            <VBtn
              variant="outlined"
              prepend-icon="tabler-clock"
              @click="abrirHistorial"
            >
              {{ $t('balances.buttons.history') }}
            </VBtn>
            <VBtn
              variant="outlined"
              prepend-icon="tabler-share"
              @click="sankeyOpen = true"
            >
              {{ $t('balances.buttons.diagram') }}
            </VBtn>
          </template>
        </VCol>
      </VRow>
    </VForm>

    <VDivider class="my-6" />

    <div v-if="tablesVisible">
      <!-- Calidad de la reconciliación -->
      <section
        v-if="calidad.flows.length"
        class="mb-6"
      >
        <div class="d-flex align-baseline flex-wrap gap-2 mb-3">
          <h5 class="text-h5">
            {{ $t('balances.quality.title') }}
          </h5>
          <small class="text-medium-emphasis">{{ $t('balances.quality.hint') }}</small>
        </div>

        <VRow class="mb-1">
          <VCol
            v-for="kpi in calidadKpis"
            :key="kpi.label"
            cols="6"
            lg="3"
          >
            <div :class="['calidad-kpi', `calidad-kpi--${kpi.variant}`]">
              <div class="calidad-kpi__value">
                {{ kpi.value }}
              </div>
              <div class="calidad-kpi__label">
                {{ kpi.label }}
              </div>
              <div
                v-if="kpi.detail"
                class="calidad-kpi__detail"
              >
                {{ kpi.detail }}
              </div>
            </div>
          </VCol>
        </VRow>

        <p
          v-if="calidad.summary.nodosSinCerrar"
          class="text-error text-body-2 mb-2"
        >
          {{ $t('balances.quality.nodesOpenList') }} {{ nodosSinCerrarTexto }}
        </p>

        <VDataTable
          :headers="calidadHeaders"
          :items="calidadFilas"
          :items-per-page="-1"
          density="compact"
          class="calidad-table"
          hide-default-footer
        >
          <template #item.ajustePct="{ value }">
            <span class="text-no-wrap">{{ value > 0 ? '+' : '' }}{{ formatPct(value) }}</span>
          </template>
          <template #item.tolerancia="{ item }">
            <span
              v-if="item.usoPct === null"
              class="text-medium-emphasis"
            >{{ $t('balances.quality.noTolerance') }}</span>
            <span
              v-else
              class="text-no-wrap"
            >−{{ item.tolInf }}% / +{{ item.tolSup }}%</span>
          </template>
          <template #item.usoPct="{ item }">
            <div
              v-if="item.usoPct !== null"
              class="d-flex align-center gap-2"
            >
              <VProgressLinear
                :model-value="Math.min(item.usoPct, 100)"
                :color="estadoColor(item.estado)"
                height="6"
                rounded
                class="flex-grow-1"
              />
              <span class="text-no-wrap text-body-2">{{ Math.round(item.usoPct) }}%</span>
            </div>
          </template>
          <template #item.estado="{ value }">
            <VChip
              :color="estadoColor(value)"
              size="small"
              label
            >
              {{ $t(`balances.quality.status.${value}`) }}
            </VChip>
          </template>
        </VDataTable>
        <VBtn
          v-if="calidad.flows.length > calidadLimite"
          variant="text"
          size="small"
          class="px-0 mt-1"
          @click="calidadVerTodo = !calidadVerTodo"
        >
          {{ calidadVerTodo ? $t('balances.quality.showLess') : $t('balances.quality.showAll', { n: calidad.flows.length }) }}
        </VBtn>
      </section>

      <VRow>
        <VCol
          cols="12"
          md="4"
        >
          <div class="table-header">
            <h5 class="text-h5">
              {{ $t('balances.tables.balances') }}
            </h5>
            <IconBtn
              variant="outlined"
              color="primary"
              @click="tblExpand(1)"
            >
              <VIcon icon="tabler-search" />
            </IconBtn>
          </div>
          <AgGridVue
            class="balance-grid"
            :theme="gridTheme"
            :column-defs="balances_fields"
            :row-data="balances_table"
            :get-row-style="getRowStyle"
            :enable-browser-tooltips="true"
            :default-col-def="defaultColDef"
            @grid-ready="p => gridBalances = p.api"
            @body-scroll="onBalancesScroll"
            @cell-value-changed="onCellValueChanged"
          />
        </VCol>
        <VCol
          cols="12"
          md="8"
        >
          <div class="table-header">
            <h5 class="text-h5">
              {{ $t('balances.tables.restrictions') }}
            </h5>
            <IconBtn
              variant="outlined"
              color="primary"
              @click="tblExpand(2)"
            >
              <VIcon icon="tabler-search" />
            </IconBtn>
          </div>
          <div ref="restriccionesWrap">
            <AgGridVue
              class="balance-grid"
              :theme="gridTheme"
              :column-defs="restricciones_fields"
              :row-data="restricciones_table"
              :get-row-style="getRowStyle"
              :default-col-def="defaultColDef"
              @grid-ready="p => gridRestricciones = p.api"
              @cell-value-changed="onCellValueChanged"
            />
          </div>
        </VCol>
      </VRow>

      <VRow class="mt-4">
        <VCol
          cols="12"
          md="5"
        >
          <div class="table-header">
            <h5 class="text-h5">
              {{ $t('balances.tables.nodeAdjustment') }}
            </h5>
            <IconBtn
              variant="outlined"
              color="primary"
              @click="tblExpand(3)"
            >
              <VIcon icon="tabler-search" />
            </IconBtn>
          </div>
          <AgGridVue
            class="balance-grid"
            :theme="gridTheme"
            :column-defs="balance_nodos_fields"
            :row-data="balance_nodos"
            :default-col-def="defaultColDef"
            @grid-ready="p => p.api.sizeColumnsToFit()"
            @row-clicked="onNodoClicked"
          />
        </VCol>
        <VCol
          cols="12"
          md="7"
        >
          <div class="table-header">
            <h5 class="text-h5">
              {{ $t('balances.tables.inventoryVariations') }}
            </h5>
            <IconBtn
              variant="outlined"
              color="primary"
              @click="tblExpand(4)"
            >
              <VIcon icon="tabler-search" />
            </IconBtn>
          </div>
          <AgGridVue
            class="balance-grid"
            :theme="gridTheme"
            :column-defs="inventarios_fields"
            :row-data="inventarios_data"
            :default-col-def="defaultColDef"
            @grid-ready="p => p.api.sizeColumnsToFit()"
          />
        </VCol>
      </VRow>

      <VDialog
        v-model="modal.open"
        max-width="1140"
      >
        <DialogCloseBtn @click="modal.open = false" />
        <VCard :title="modal.title">
          <VCardText>
            <AgGridVue
              class="balance-grid"
              :theme="gridTheme"
              :column-defs="modal.fields"
              :row-data="modal.data"
              :get-row-style="modal.kind <= 2 ? getRowStyle : undefined"
              :default-col-def="defaultColDef"
              @cell-value-changed="onCellValueChanged"
            />
          </VCardText>
        </VCard>
      </VDialog>
    </div>

    <VDialog
      v-model="sankeyOpen"
      max-width="1140"
    >
      <DialogCloseBtn @click="sankeyOpen = false" />
      <VCard :title="$t('balances.sankey.title')">
        <VCardText>
          <BalanceSankey
            v-if="sankeyOpen"
            :graph="flowGraph"
          />
        </VCardText>
      </VCard>
    </VDialog>

    <VDialog
      v-model="historyOpen"
      max-width="800"
    >
      <DialogCloseBtn @click="historyOpen = false" />
      <VCard :title="$t('balances.history.title')">
        <VCardText>
          <VDataTable
            :headers="corridasHeaders"
            :items="corridas"
            :loading="corridasLoading"
            :no-data-text="$t('balances.history.empty')"
            :items-per-page="-1"
            density="compact"
            hide-default-footer
          >
            <template #item.created_at="{ value }">
              {{ formatFecha(value) }}
            </template>
            <template #item.tipo="{ value }">
              {{ $t(`balances.history.types.${value}`) }}
            </template>
            <template #item.acciones="{ item, index }">
              <span
                v-if="index === 0"
                class="text-medium-emphasis"
              >{{ $t('balances.history.current') }}</span>
              <VBtn
                v-else-if="canWrite"
                size="small"
                variant="outlined"
                @click="restaurarCorrida(item)"
              >
                {{ $t('balances.history.restore') }}
              </VBtn>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Overwrite vs. copy of a reopened balance; it can only be closed with one of the two choices. -->
    <VDialog
      :model-value="editDecision.open"
      max-width="500"
      persistent
    >
      <VCard :title="$t('balances.editDecision.title')">
        <VCardText>{{ $t('balances.editDecision.message') }}</VCardText>
        <VCardActions class="justify-end">
          <VBtn
            color="secondary"
            variant="outlined"
            @click="editDecision.resolve(false)"
          >
            {{ $t('balances.editDecision.overwrite') }}
          </VBtn>
          <VBtn
            variant="elevated"
            @click="editDecision.resolve(true)"
          >
            {{ $t('balances.editDecision.saveAsNew') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VOverlay
      :model-value="isLoading"
      persistent
      class="align-center justify-center"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
    </VOverlay>
  </div>
</template>

<style lang="scss">
.balances-workspace {
  .balance-grid {
    width: 100%;
    height: 500px;

    .ag-cell {
      font-variant-numeric: tabular-nums;
    }

    // Numbers line up on the right; the pinned name column stays left-aligned.
    .ag-center-cols-container .ag-cell {
      text-align: right;
    }

    .ag-header-viewport .ag-header-cell-label {
      justify-content: flex-end;
      text-align: right;
    }

    .ag-pinned-left-cols-container .ag-cell {
      font-weight: 500;
    }

    .ag-header-cell-text {
      letter-spacing: 0.02em;
    }

    // Computed by the solver (balance) or derived from tolerances (calculated): soft tint, same meaning.
    .ag-cell.balance,
    .ag-cell.calculated {
      background-color: rgba(0, 207, 232, 12%);
      color: #0b7d8c;
      font-weight: 600;
    }
  }

  &--dark .balance-grid {
    .ag-cell.balance,
    .ag-cell.calculated {
      background-color: rgba(0, 207, 232, 14%);
      color: #5fdcec;
    }
  }

  .table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-block-end: 1rem;
  }

  .calidad-kpi {
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    background: rgba(130, 134, 139, 8%);
    block-size: 100%;
    border-inline-start: 4px solid currentcolor;

    &--success { color: rgb(var(--v-theme-success)); }
    &--warning { color: rgb(var(--v-theme-warning)); }
    &--danger { color: rgb(var(--v-theme-error)); }
    &--neutral { color: rgb(var(--v-theme-secondary)); }

    &__value {
      font-size: 1.5rem;
      font-variant-numeric: tabular-nums;
      font-weight: 600;
      line-height: 1.2;
    }

    &__label,
    &__detail {
      color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
      font-size: 0.8rem;
    }

    &__detail {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  }

  .calidad-table td {
    font-variant-numeric: tabular-nums;
  }
}
</style>
