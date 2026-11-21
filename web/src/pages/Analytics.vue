<script setup lang="ts">
import { ArrowLeft, RefreshCw } from '@lucide/vue'
import type { EChartsCoreOption } from 'echarts/core'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import EChart, { type Tokens } from '@/components/charts/EChart.vue'
import Notice from '@/components/Notice.vue'
import { api, errorMessage } from '@/lib/api'
import { fecha, fmt, valleColor } from '@/lib/format'

// Same /api/analytics endpoints as v1 (they stay as they are).
interface Punto { period: string; period_label: string; total: number }
interface Dashboard {
  summary: { sessions_imported: number; balances_corridos: number; balances_guardados: number; usuarios_activos: number }
  by_user: { user_id: number; nombre: string; total: number }[]
  by_valle: { valle_id: number; nombre: string; total: number }[]
  by_proceso: { proceso_id: number; nombre: string; valle_nombre: string; label: string; total: number }[]
  timeseries: Punto[]
  cached_at: string
}
interface Opciones {
  users: { id: number; nombre: string }[]
  valles: { id: number; nombre: string }[]
  procesos: { id: number; nombre: string; valle_id: number }[]
}

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const hoy = new Date()
const f = reactive({
  from: iso(new Date(hoy.getFullYear(), hoy.getMonth() - 6, hoy.getDate())),
  to: iso(hoy),
  valle_id: '',
  proceso_id: '',
  user_id: '',
  metric: 'imported' as 'imported' | 'correr' | 'both',
  group_by: 'month' as 'month' | 'day',
})
// Drill-down into one month keeps the range the user had, to go back to it.
const mes = ref<{ label: string; from: string; to: string } | null>(null)

const opciones = ref<Opciones>({ users: [], valles: [], procesos: [] })
const data = ref<Dashboard | null>(null)
const loading = ref(false)
const error = ref('')

const procesos = computed(() => opciones.value.procesos.filter(p => !f.valle_id || String(p.valle_id) === f.valle_id))

let seq = 0
async function load(refresh = false) {
  const mine = ++seq
  loading.value = true
  const query = Object.fromEntries(Object.entries({ ...f, ...(refresh ? { refresh: 1 } : {}) }).filter(([, v]) => v !== ''))
  try {
    const res = await api<Dashboard>('/analytics/dashboard', { query })
    if (mine !== seq) return
    data.value = res
    error.value = ''
  } catch (e) {
    if (mine === seq) error.value = errorMessage(e, 'No se pudieron cargar los indicadores.')
  } finally {
    if (mine === seq) loading.value = false
  }
}
async function loadOpciones(refresh = false) {
  try {
    opciones.value = await api<Opciones>('/analytics/filter-options', { query: refresh ? { refresh: 1 } : {} })
  } catch {} // the selects just stay with "Todos"
}
onMounted(() => {
  load()
  loadOpciones()
})

watch(() => f.valle_id, () => {
  if (f.proceso_id && !procesos.value.some(p => String(p.id) === f.proceso_id)) f.proceso_id = ''
})
watch(() => [f.valle_id, f.proceso_id, f.user_id, f.metric], () => load())
// Changing the dates leaves the drill-down: the range is the user's again.
watch(() => [f.from, f.to, f.group_by], () => {
  if (mes.value && (f.from !== mes.value.from || f.to !== mes.value.to)) {
    mes.value = null
    f.group_by = 'month'
  }
  load()
})

let rango: { from: string; to: string } | null = null
function verDias(i: number) {
  const p = data.value?.timeseries[i]
  const m = p?.period.match(/^(\d{4})-(\d{2})$/)
  if (f.group_by !== 'month' || !p || !m) return
  rango = { from: f.from, to: f.to }
  const ultimo = new Date(Number(m[1]), Number(m[2]), 0).getDate()
  mes.value = { label: p.period_label, from: `${m[1]}-${m[2]}-01`, to: `${m[1]}-${m[2]}-${String(ultimo).padStart(2, '0')}` }
  f.group_by = 'day'
  Object.assign(f, { from: mes.value.from, to: mes.value.to })
}
function volver() {
  mes.value = null
  f.group_by = 'month'
  if (rango) Object.assign(f, rango)
}
function actualizar() {
  load(true)
  loadOpciones(true)
}

const metrica = computed(() => ({ imported: 'Sesiones importadas', correr: 'Balances corridos', both: 'Importaciones y corridas' })[f.metric])
const kpis = computed(() => {
  const s = data.value?.summary
  return [
    { label: 'Sesiones importadas', value: s?.sessions_imported },
    { label: 'Balances corridos', value: s?.balances_corridos },
    { label: 'Balances guardados', value: s?.balances_guardados },
    { label: 'Usuarios activos', value: s?.usuarios_activos },
  ]
})

// ECharts draws on canvas: CSS variables have to be resolved to colors first.
function cssColor(c: string) {
  const v = c.match(/^var\((--[^)]+)\)$/)?.[1]
  return v ? getComputedStyle(document.documentElement).getPropertyValue(v).trim() || c : c
}
const axis = (t: Tokens) => ({ axisLine: { lineStyle: { color: t.line } }, axisTick: { show: false }, axisLabel: { color: t.inkMuted }, splitLine: { lineStyle: { color: t.line } } })
const tooltip = (t: Tokens) => ({ backgroundColor: t.surface, borderColor: t.line, textStyle: { color: t.ink } })

const serie = computed(() => data.value?.timeseries ?? [])
function serieOption(t: Tokens): EChartsCoreOption {
  return {
    grid: { left: 8, right: 8, top: 12, bottom: 4, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, ...tooltip(t) },
    xAxis: { type: 'category', data: serie.value.map(p => p.period_label), ...axis(t), splitLine: { show: false } },
    yAxis: { type: 'value', minInterval: 1, ...axis(t) },
    series: [{
      type: 'bar', name: metrica.value, data: serie.value.map(p => p.total), barMaxWidth: 36,
      itemStyle: { color: t.primary, borderRadius: [3, 3, 0, 0] },
      cursor: f.group_by === 'month' ? 'pointer' : 'default',
    }],
  }
}
function valleOption(t: Tokens): EChartsCoreOption {
  const rows = data.value?.by_valle ?? []
  return {
    tooltip: { trigger: 'item', formatter: '{b}: {c} ({d} %)', ...tooltip(t) },
    legend: { bottom: 0, textStyle: { color: t.inkMuted }, icon: 'circle', itemWidth: 8, itemHeight: 8 },
    series: [{
      type: 'pie', radius: ['52%', '78%'], center: ['50%', '44%'], avoidLabelOverlap: true, label: { show: false },
      itemStyle: { borderColor: t.surface, borderWidth: 2 },
      data: rows.map(r => ({ name: r.nombre, value: r.total, itemStyle: { color: cssColor(valleColor(r.nombre)) } })),
    }],
  }
}
function barrasOption(rows: { label: string; total: number; color: string }[]) {
  return (t: Tokens): EChartsCoreOption => ({
    grid: { left: 8, right: 24, top: 4, bottom: 4, containLabel: true },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, ...tooltip(t) },
    xAxis: { type: 'value', minInterval: 1, ...axis(t) },
    yAxis: { type: 'category', inverse: true, data: rows.map(r => r.label), ...axis(t), splitLine: { show: false } },
    series: [{
      type: 'bar', name: metrica.value, barMaxWidth: 18,
      data: rows.map(r => ({ value: r.total, itemStyle: { color: r.color === 'primary' ? t.primary : r.color, borderRadius: [0, 3, 3, 0] } })),
    }],
  })
}
const porProceso = computed(() => (data.value?.by_proceso ?? []).slice(0, 10).map(p => ({ label: p.label, total: p.total, color: cssColor(valleColor(p.valle_nombre)) })))
const porUsuario = computed(() => (data.value?.by_user ?? []).slice(0, 10).map(u => ({ label: u.nombre, total: u.total, color: 'primary' })))
const alto = (n: number) => `${Math.max(8, n * 2 + 2)}rem`

const label = 'mb-1 block text-xs font-medium text-ink-muted'
const card = 'rounded-lg border border-line bg-surface p-4'
</script>

<template>
  <div class="mx-auto max-w-7xl">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="font-display text-xl font-semibold">Analytics</h1>
        <p class="mt-1 text-ink-muted">
          Uso de la plataforma por período, valle, proceso y usuario.
          <span v-if="data" class="whitespace-nowrap">Datos de {{ fecha(data.cached_at, true) }}</span>
        </p>
      </div>
      <button type="button" class="btn btn-quiet" :disabled="loading" @click="actualizar">
        <RefreshCw class="size-4" :class="{ 'animate-spin': loading }" aria-hidden="true" /> Actualizar
      </button>
    </div>

    <section aria-label="Filtros" class="mt-6 grid grid-cols-2 gap-3 rounded-lg border border-line bg-surface p-3 sm:grid-cols-3 lg:grid-cols-6">
      <div>
        <label for="a-from" :class="label">Desde</label>
        <input id="a-from" v-model.lazy="f.from" type="date" class="field" :max="f.to">
      </div>
      <div>
        <label for="a-to" :class="label">Hasta</label>
        <input id="a-to" v-model.lazy="f.to" type="date" class="field" :min="f.from">
      </div>
      <div>
        <label for="a-valle" :class="label">Valle</label>
        <select id="a-valle" v-model="f.valle_id" class="field">
          <option value="">Todos</option>
          <option v-for="v in opciones.valles" :key="v.id" :value="String(v.id)">{{ v.nombre }}</option>
        </select>
      </div>
      <div>
        <label for="a-proceso" :class="label">Proceso</label>
        <select id="a-proceso" v-model="f.proceso_id" class="field">
          <option value="">Todos</option>
          <option v-for="p in procesos" :key="p.id" :value="String(p.id)">{{ p.nombre }}</option>
        </select>
      </div>
      <div>
        <label for="a-user" :class="label">Usuario</label>
        <select id="a-user" v-model="f.user_id" class="field">
          <option value="">Todos</option>
          <option v-for="u in opciones.users" :key="u.id" :value="String(u.id)">{{ u.nombre }}</option>
        </select>
      </div>
      <div>
        <label for="a-metric" :class="label">Métrica</label>
        <select id="a-metric" v-model="f.metric" class="field">
          <option value="imported">Importaciones</option>
          <option value="correr">Corridas</option>
          <option value="both">Ambas</option>
        </select>
      </div>
    </section>

    <p class="sr-only" role="status" aria-live="polite">{{ loading ? 'Cargando indicadores…' : data ? 'Indicadores actualizados.' : '' }}</p>
    <Notice v-if="error" tone="danger" class="mt-4">{{ error }}</Notice>

    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4" :aria-busy="loading">
      <div v-for="k in kpis" :key="k.label" :class="card">
        <p class="text-xs font-medium text-ink-muted">{{ k.label }}</p>
        <p v-if="k.value !== undefined" class="mt-1 font-display text-2xl font-semibold">{{ fmt(k.value) }}</p>
        <div v-else class="mt-2 h-7 w-16 animate-pulse rounded bg-surface-2" aria-hidden="true" />
      </div>
    </div>

    <div v-if="data" class="mt-4 grid gap-4 lg:grid-cols-3" :aria-busy="loading" :class="{ 'opacity-60 transition-opacity': loading }">
      <section :class="card" class="lg:col-span-2">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
          <button v-if="mes" type="button" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline" @click="volver">
            <ArrowLeft class="size-3.5" aria-hidden="true" /> Volver a meses
          </button>
          <span v-else class="text-xs text-ink-muted">Haz clic en un mes para verlo por día.</span>
          <label v-if="!mes && serie.length" class="flex items-center gap-2 text-xs text-ink-muted">
            <span class="sr-only sm:not-sr-only">Ver por día</span>
            <select class="field h-8 w-auto py-0 text-xs" aria-label="Ver un mes por día" @change="e => verDias(Number((e.target as HTMLSelectElement).value))">
              <option value="" selected disabled>Elegir mes</option>
              <option v-for="(p, i) in serie" :key="p.period" :value="i">{{ p.period_label }}</option>
            </select>
          </label>
        </div>
        <EChart
          v-if="serie.length"
          :titulo="mes ? `${metrica} en ${mes.label}` : `${metrica} por mes`"
          :resumen="`${fmt(serie.reduce((s, p) => s + p.total, 0))} en ${serie.length} ${mes ? 'días con actividad' : 'meses'}.`"
          :option="serieOption"
          alto="18rem"
          @select="verDias"
        />
        <p v-else class="grid h-72 place-items-center text-ink-muted">Sin actividad en el período.</p>
      </section>

      <section :class="card">
        <EChart
          v-if="data.by_valle.length"
          titulo="Por valle"
          :resumen="data.by_valle.map(v => `${v.nombre}: ${fmt(v.total)}`).join(', ') + '.'"
          :option="valleOption"
          alto="18rem"
        />
        <p v-else class="grid h-72 place-items-center text-ink-muted">Sin datos por valle.</p>
      </section>

      <section :class="card" class="lg:col-span-2">
        <EChart
          v-if="porProceso.length"
          titulo="Por proceso"
          :resumen="`Los ${porProceso.length} procesos con más ${f.metric === 'correr' ? 'corridas' : 'importaciones'}; el color es el valle.`"
          :option="barrasOption(porProceso)"
          :alto="alto(porProceso.length)"
        />
        <p v-else class="py-10 text-center text-ink-muted">Sin datos por proceso.</p>
      </section>

      <section :class="card">
        <EChart
          v-if="porUsuario.length"
          titulo="Usuarios más activos"
          :resumen="`Los ${porUsuario.length} usuarios con más actividad en el período.`"
          :option="barrasOption(porUsuario)"
          :alto="alto(porUsuario.length)"
        />
        <p v-else class="py-10 text-center text-ink-muted">Sin actividad de usuarios.</p>
      </section>
    </div>
    <div v-else-if="!error" class="mt-4 grid gap-4 lg:grid-cols-3" aria-hidden="true">
      <div :class="card" class="h-80 animate-pulse lg:col-span-2" />
      <div :class="card" class="h-80 animate-pulse" />
    </div>
  </div>
</template>
