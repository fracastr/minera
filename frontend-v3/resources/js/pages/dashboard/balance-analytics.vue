<script setup>
import { useTheme } from 'vuetify'

definePage({
  meta: {
    action: 'read',
    subject: 'DashboardAnalytics',
  },
})

const { error } = useNotify()
const vuetifyTheme = useTheme()

function defaultFromDate() {
  const date = new Date()

  date.setMonth(date.getMonth() - 6)

  return date.toISOString().slice(0, 10)
}

function defaultToDate() {
  return new Date().toISOString().slice(0, 10)
}

const loading = ref(false)

const filters = ref({
  from: defaultFromDate(),
  to: defaultToDate(),
  valle_id: '',
  proceso_id: '',
  user_id: '',
  metric: 'imported',
  group_by: 'month',
})

const filterOptions = ref({ users: [], valles: [], procesos: [] })

const summary = ref({
  sessions_imported: 0,
  balances_corridos: 0,
  balances_guardados: 0,
  usuarios_activos: 0,
})

const byUser = ref([])
const byValle = ref([])
const byProceso = ref([])
const timeseries = ref([])
const cachedAt = ref(null)
const timeseriesMode = ref('month')
const selectedMonth = ref(null)
const savedRange = ref(null)

const metricOptions = [
  { value: 'imported', text: 'Sesiones importadas' },
  { value: 'correr', text: 'Balances corridos' },
]

const kpis = computed(() => [
  { label: 'Sesiones importadas', value: summary.value.sessions_imported, icon: 'tabler-cloud-upload', color: 'primary' },
  { label: 'Balances corridos', value: summary.value.balances_corridos, icon: 'tabler-player-play', color: 'warning' },
  { label: 'Balances guardados', value: summary.value.balances_guardados, icon: 'tabler-device-floppy', color: 'success' },
  { label: 'Usuarios activos', value: summary.value.usuarios_activos, icon: 'tabler-users', color: 'info' },
])

const cachedAtLabel = computed(() => cachedAt.value
  ? new Date(cachedAt.value).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' })
  : '')

// Chart colors follow the Vuetify theme, so light and dark both read well.
const theme = computed(() => vuetifyTheme.current.value)
const isDark = computed(() => theme.value.dark)
const chartThemeKey = computed(() => isDark.value ? 'dark' : 'light')
const chartTextColor = computed(() => `rgba(${hexToRgb(theme.value.colors['on-surface'])},${theme.value.variables['medium-emphasis-opacity']})`)
const chartGridColor = computed(() => `rgba(${hexToRgb(String(theme.value.variables['border-color']))},${theme.value.variables['border-opacity']})`)
const colors = computed(() => theme.value.colors)

const apexBaseOptions = computed(() => ({
  chart: {
    foreColor: chartTextColor.value,
    background: 'transparent',
    toolbar: { show: false },
  },
  theme: { mode: isDark.value ? 'dark' : 'light' },
  grid: { borderColor: chartGridColor.value },
  tooltip: { theme: isDark.value ? 'dark' : 'light', fillSeriesColor: false },
  dataLabels: { enabled: false },
}))

const seriesName = computed(() => filters.value.metric === 'correr' ? 'Corridas' : 'Importaciones')

const valleNameById = computed(() => Object.fromEntries(filterOptions.value.valles.map(valle => [valle.id, valle.nombre])))

const availableProcesos = computed(() => {
  const procesos = filterOptions.value.procesos || []
  if (!filters.value.valle_id)
    return procesos

  return procesos.filter(proceso => String(proceso.valle_id) === String(filters.value.valle_id))
})

const procesoOptions = computed(() => [
  { value: '', text: 'Todos los procesos' },
  ...availableProcesos.value.map(proceso => {
    const valleNombre = valleNameById.value[proceso.valle_id]
    const suffix = !filters.value.valle_id && valleNombre ? ` (${valleNombre})` : ''

    return { value: String(proceso.id), text: `${proceso.nombre}${suffix}` }
  }),
])

const valleOptions = computed(() => [
  { value: '', text: 'Todos los valles' },
  ...filterOptions.value.valles.map(valle => ({ value: String(valle.id), text: valle.nombre })),
])

const userOptions = computed(() => [
  { value: '', text: 'Todos los usuarios' },
  ...filterOptions.value.users.map(user => ({ value: String(user.id), text: user.nombre })),
])

const queryParams = computed(() => {
  const { from, to, metric, group_by, valle_id, proceso_id, user_id } = filters.value
  const params = { from, to, metric, group_by }
  if (valle_id)
    params.valle_id = valle_id
  if (proceso_id)
    params.proceso_id = proceso_id
  if (user_id)
    params.user_id = user_id

  return params
})

const horizontalBarOptions = (labels, color) => ({
  ...apexBaseOptions.value,
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  colors: [color],
  xaxis: {
    categories: labels,
    labels: { style: { colors: chartTextColor.value } },
    axisBorder: { color: chartGridColor.value },
    axisTicks: { color: chartGridColor.value },
  },
  yaxis: { labels: { style: { colors: chartTextColor.value }, maxWidth: 160 } },
})

const byUserLabels = computed(() => byUser.value.map(item => item.nombre))
const byUserSeries = computed(() => [{ name: seriesName.value, data: byUser.value.map(item => item.total) }])
const byUserOptions = computed(() => horizontalBarOptions(byUserLabels.value, colors.value.primary))

const byProcesoLabels = computed(() => byProceso.value.map(item => item.label || item.nombre))
const byProcesoSeries = computed(() => [{ name: seriesName.value, data: byProceso.value.map(item => item.total) }])
const byProcesoOptions = computed(() => horizontalBarOptions(byProcesoLabels.value, colors.value.success))

const byValleLabels = computed(() => byValle.value.map(item => item.nombre))
const byValleSeries = computed(() => byValle.value.map(item => item.total))

const byValleOptions = computed(() => ({
  ...apexBaseOptions.value,
  labels: byValleLabels.value,
  legend: { position: 'bottom', labels: { colors: chartTextColor.value } },
  dataLabels: {
    enabled: true,
    style: { fontSize: '13px', fontWeight: 600, colors: ['#fff'] },
    dropShadow: { enabled: true, top: 1, left: 1, blur: 2, color: '#000', opacity: 0.85 },
  },
  stroke: { colors: [theme.value.colors.surface] },
  colors: [colors.value.primary, colors.value.warning, colors.value.success, colors.value.info, colors.value.error],
  tooltip: { ...apexBaseOptions.value.tooltip, style: { fontSize: '13px' } },
}))

const timeseriesTitle = computed(() => timeseriesMode.value === 'day' && selectedMonth.value
  ? `Detalle diario — ${selectedMonth.value.label}`
  : 'Evolución mensual')

const timeseriesCategories = computed(() => timeseries.value.map(item => item.period_label || item.period))
const timeseriesSeries = computed(() => [{ name: seriesName.value, data: timeseries.value.map(item => item.total) }])

const timeseriesOptions = computed(() => ({
  ...apexBaseOptions.value,
  plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
  chart: {
    ...apexBaseOptions.value.chart,
    events: {
      dataPointSelection(event, chartContext, config) {
        if (timeseriesMode.value === 'month')
          drillIntoMonth(config.dataPointIndex)
      },
    },
  },
  states: { active: { filter: { type: 'none' } } },
  colors: [colors.value.primary],
  xaxis: {
    categories: timeseriesCategories.value,
    labels: { style: { colors: chartTextColor.value }, rotate: -35, hideOverlappingLabels: true },
    axisBorder: { color: chartGridColor.value },
    axisTicks: { color: chartGridColor.value },
  },
  yaxis: { labels: { style: { colors: chartTextColor.value } } },
}))

async function loadFilterOptions() {
  try {
    const data = await $api('/analytics/filter-options', { query: { refresh: 1 } })

    filterOptions.value = {
      users: data.users || [],
      valles: data.valles || [],
      procesos: data.procesos || [],
    }
  }
  catch {}
}

async function loadAll(forceRefresh = false) {
  loading.value = true

  const query = { ...queryParams.value }
  if (forceRefresh === true)
    query.refresh = 1

  try {
    const data = await $api('/analytics/dashboard', { query })

    summary.value = data.summary
    byUser.value = data.by_user
    byValle.value = data.by_valle
    byProceso.value = data.by_proceso || []
    timeseries.value = data.timeseries
    cachedAt.value = data.cached_at
  }
  catch {
    error('No se pudieron cargar los datos del dashboard.')
  }
  finally {
    loading.value = false
  }
}

function onValleChange() {
  if (filters.value.proceso_id) {
    const isValid = availableProcesos.value.some(proceso => String(proceso.id) === String(filters.value.proceso_id))
    if (!isValid)
      filters.value.proceso_id = ''
  }
  loadAll()
}

function onDateChange() {
  if (timeseriesMode.value === 'day') {
    timeseriesMode.value = 'month'
    filters.value.group_by = 'month'
    selectedMonth.value = null
    savedRange.value = null
  }
  loadAll()
}

function drillIntoMonth(index) {
  const point = timeseries.value[index]
  if (!point || !point.period || timeseriesMode.value !== 'month')
    return

  const match = point.period.match(/^(\d{4})-(\d{2})$/)
  if (!match)
    return

  const lastDay = new Date(Number(match[1]), Number(match[2]), 0).getDate()
  const from = `${match[1]}-${match[2]}-01`
  const to = `${match[1]}-${match[2]}-${String(lastDay).padStart(2, '0')}`

  savedRange.value = { from: filters.value.from, to: filters.value.to, group_by: filters.value.group_by }
  selectedMonth.value = { period: point.period, label: point.period_label || point.period, from, to }
  timeseriesMode.value = 'day'
  Object.assign(filters.value, { from, to, group_by: 'day' })
  loadAll(true)
}

function exitMonthDrilldown() {
  if (savedRange.value) {
    Object.assign(filters.value, {
      from: savedRange.value.from,
      to: savedRange.value.to,
      group_by: savedRange.value.group_by || 'month',
    })
  }
  else {
    filters.value.group_by = 'month'
  }
  timeseriesMode.value = 'month'
  selectedMonth.value = null
  savedRange.value = null
  loadAll(true)
}

function loadCharts() {
  if (timeseriesMode.value === 'day')
    exitMonthDrilldown()
  else
    loadAll()
}

onMounted(() => {
  loadFilterOptions()
  loadAll()
})
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex flex-wrap justify-space-between align-start gap-4 mb-4">
          <div>
            <h4 class="text-h4 mb-1">
              Dashboard de balances
            </h4>
            <p class="text-body-1 mb-0">
              Actividad por sesiones importadas y balances corridos. Fechas según la importación del Excel.
            </p>
            <p
              v-if="cachedAtLabel"
              class="text-caption text-disabled mb-0"
            >
              Datos calculados el {{ cachedAtLabel }}
            </p>
          </div>
          <VBtn
            variant="outlined"
            size="small"
            prepend-icon="tabler-refresh"
            :disabled="loading"
            @click="loadAll(true)"
          >
            Actualizar
          </VBtn>
        </div>

        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <AppTextField
              v-model="filters.from"
              label="Desde"
              type="date"
              @change="onDateChange"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <AppTextField
              v-model="filters.to"
              label="Hasta"
              type="date"
              @change="onDateChange"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <AppSelect
              v-model="filters.valle_id"
              label="Valle"
              :items="valleOptions"
              item-title="text"
              @update:model-value="onValleChange"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <AppSelect
              v-model="filters.proceso_id"
              label="Proceso"
              :items="procesoOptions"
              item-title="text"
              @update:model-value="loadAll()"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <AppSelect
              v-model="filters.user_id"
              label="Usuario"
              :items="userOptions"
              item-title="text"
              @update:model-value="loadAll()"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="filters.metric"
              label="Métrica"
              :items="metricOptions"
              item-title="text"
              @update:model-value="loadCharts"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div class="position-relative">
      <VOverlay
        :model-value="loading"
        contained
        persistent
        class="align-center justify-center"
      >
        <div class="text-center">
          <VProgressCircular
            indeterminate
            color="primary"
          />
          <p class="text-body-2 mt-2 mb-0">
            Cargando datos…
          </p>
        </div>
      </VOverlay>

      <VRow>
        <VCol
          v-for="kpi in kpis"
          :key="kpi.label"
          cols="12"
          sm="6"
          lg="3"
        >
          <VCard class="h-100">
            <VCardText class="d-flex align-center gap-4">
              <VAvatar
                size="48"
                rounded
                variant="tonal"
                :color="kpi.color"
              >
                <VIcon
                  :icon="kpi.icon"
                  size="26"
                />
              </VAvatar>
              <div>
                <p class="text-body-2 mb-1">
                  {{ kpi.label }}
                </p>
                <h4 class="text-h4">
                  {{ kpi.value }}
                </h4>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="8"
        >
          <VCard>
            <VCardItem>
              <VCardTitle class="d-flex align-center gap-2">
                <VIcon
                  :icon="timeseriesMode === 'day' ? 'tabler-calendar' : 'tabler-chart-bar'"
                  size="20"
                  color="primary"
                />
                {{ timeseriesTitle }}
              </VCardTitle>
              <template
                v-if="timeseriesMode === 'day'"
                #append
              >
                <VBtn
                  size="small"
                  variant="outlined"
                  prepend-icon="tabler-arrow-left"
                  @click="exitMonthDrilldown"
                >
                  Volver a meses
                </VBtn>
              </template>
            </VCardItem>
            <VCardText>
              <p
                v-if="timeseriesMode === 'month'"
                class="text-body-2 mb-2"
              >
                Haz clic en un mes para ver el detalle por día.
              </p>
              <VueApexCharts
                v-if="timeseriesCategories.length"
                :key="`timeseries-${chartThemeKey}-${timeseriesMode}`"
                type="bar"
                height="320"
                :options="timeseriesOptions"
                :series="timeseriesSeries"
              />
              <p
                v-else
                class="text-center text-body-1 my-6"
              >
                Sin datos para el período seleccionado.
              </p>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="4"
        >
          <VCard class="h-100">
            <VCardItem>
              <VCardTitle class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-map-pin"
                  size="20"
                  color="warning"
                />
                Por valle
              </VCardTitle>
            </VCardItem>
            <VCardText>
              <VueApexCharts
                v-if="byValleLabels.length"
                :key="`valle-${chartThemeKey}`"
                type="donut"
                height="320"
                :options="byValleOptions"
                :series="byValleSeries"
              />
              <p
                v-else
                class="text-center text-body-1 my-6"
              >
                Sin datos para el período seleccionado.
              </p>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="6"
        >
          <VCard>
            <VCardItem>
              <VCardTitle class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-stack-2"
                  size="20"
                  color="success"
                />
                Por proceso
              </VCardTitle>
            </VCardItem>
            <VCardText>
              <VueApexCharts
                v-if="byProcesoLabels.length"
                :key="`proceso-${chartThemeKey}`"
                type="bar"
                height="320"
                :options="byProcesoOptions"
                :series="byProcesoSeries"
              />
              <p
                v-else
                class="text-center text-body-1 my-6"
              >
                Sin datos para el período seleccionado.
              </p>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="6"
        >
          <VCard>
            <VCardItem>
              <VCardTitle class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-award"
                  size="20"
                  color="primary"
                />
                Top usuarios
              </VCardTitle>
            </VCardItem>
            <VCardText>
              <VueApexCharts
                v-if="byUserLabels.length"
                :key="`users-${chartThemeKey}`"
                type="bar"
                height="320"
                :options="byUserOptions"
                :series="byUserSeries"
              />
              <p
                v-else
                class="text-center text-body-1 my-6"
              >
                Sin datos para el período seleccionado.
              </p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>
