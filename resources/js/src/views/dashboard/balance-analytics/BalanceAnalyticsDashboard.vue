<template>
  <div class="balance-analytics">
    <b-card class="mb-2">
      <div class="d-flex flex-wrap justify-content-between align-items-start mb-1">
        <div>
          <h2 class="balance-analytics__title mb-25">
            Dashboard de balances
          </h2>
          <p class="balance-analytics__subtitle mb-0">
            Actividad por sesiones importadas y balances corridos. Fechas según la importación del Excel.
          </p>
        </div>
        <b-button
          variant="outline-primary"
          size="sm"
          :disabled="loading"
          @click="loadAll(true)"
        >
          <feather-icon
            icon="RefreshCwIcon"
            size="14"
            class="mr-50"
          />
          Actualizar
        </b-button>
      </div>

      <b-row class="mt-1">
        <b-col
          md="2"
          sm="6"
          class="mb-1"
        >
          <label class="balance-analytics__label small mb-25">Desde</label>
          <b-form-input
            v-model="filters.from"
            type="date"
            @change="onDateChange"
          />
        </b-col>
        <b-col
          md="2"
          sm="6"
          class="mb-1"
        >
          <label class="balance-analytics__label small mb-25">Hasta</label>
          <b-form-input
            v-model="filters.to"
            type="date"
            @change="onDateChange"
          />
        </b-col>
        <b-col
          md="2"
          sm="6"
          class="mb-1"
        >
          <label class="balance-analytics__label small mb-25">Valle</label>
          <b-form-select
            v-model="filters.valle_id"
            :options="valleOptions"
            @change="onValleChange"
          />
        </b-col>
        <b-col
          md="2"
          sm="6"
          class="mb-1"
        >
          <label class="balance-analytics__label small mb-25">Proceso</label>
          <b-form-select
            v-model="filters.proceso_id"
            :options="procesoOptions"
            @input="onProcesoChange"
          />
        </b-col>
        <b-col
          md="2"
          sm="6"
          class="mb-1"
        >
          <label class="balance-analytics__label small mb-25">Usuario</label>
          <b-form-select
            v-model="filters.user_id"
            :options="userOptions"
            @change="loadAll"
          />
        </b-col>
        <b-col
          md="2"
          sm="12"
          class="mb-1"
        >
          <label class="balance-analytics__label small mb-25">Métrica</label>
          <b-form-select
            v-model="filters.metric"
            :options="metricOptions"
            @change="loadCharts"
          />
        </b-col>
      </b-row>
    </b-card>

    <div class="balance-analytics__body position-relative">
      <b-overlay
        :show="loading"
        rounded="sm"
        spinner-variant="primary"
        class="balance-analytics__overlay"
      >
        <template #overlay>
          <div class="balance-analytics__loading text-center">
            <b-spinner variant="primary" />
            <p class="balance-analytics__loading-text mt-1 mb-0">
              Cargando datos…
            </p>
          </div>
        </template>

    <b-row>
      <b-col
        lg="3"
        sm="6"
        class="mb-2"
      >
        <b-card class="h-100">
          <div class="d-flex align-items-center">
            <b-avatar
              size="48"
              variant="light-primary"
              class="mr-1"
            >
              <feather-icon
                icon="UploadCloudIcon"
                size="24"
              />
            </b-avatar>
            <div>
              <p class="balance-analytics__stat-label mb-25 small">
                Sesiones importadas
              </p>
              <h3 class="balance-analytics__stat-value mb-0">
                {{ summary.sessions_imported }}
              </h3>
            </div>
          </div>
        </b-card>
      </b-col>
      <b-col
        lg="3"
        sm="6"
        class="mb-2"
      >
        <b-card class="h-100">
          <div class="d-flex align-items-center">
            <b-avatar
              size="48"
              variant="light-warning"
              class="mr-1"
            >
              <feather-icon
                icon="PlayCircleIcon"
                size="24"
              />
            </b-avatar>
            <div>
              <p class="balance-analytics__stat-label mb-25 small">
                Balances corridos
              </p>
              <h3 class="balance-analytics__stat-value mb-0">
                {{ summary.balances_corridos }}
              </h3>
            </div>
          </div>
        </b-card>
      </b-col>
      <b-col
        lg="3"
        sm="6"
        class="mb-2"
      >
        <b-card class="h-100">
          <div class="d-flex align-items-center">
            <b-avatar
              size="48"
              variant="light-success"
              class="mr-1"
            >
              <feather-icon
                icon="SaveIcon"
                size="24"
              />
            </b-avatar>
            <div>
              <p class="balance-analytics__stat-label mb-25 small">
                Balances guardados
              </p>
              <h3 class="balance-analytics__stat-value mb-0">
                {{ summary.balances_guardados }}
              </h3>
            </div>
          </div>
        </b-card>
      </b-col>
      <b-col
        lg="3"
        sm="6"
        class="mb-2"
      >
        <b-card class="h-100">
          <div class="d-flex align-items-center">
            <b-avatar
              size="48"
              variant="light-info"
              class="mr-1"
            >
              <feather-icon
                icon="UsersIcon"
                size="24"
              />
            </b-avatar>
            <div>
              <p class="balance-analytics__stat-label mb-25 small">
                Usuarios activos
              </p>
              <h3 class="balance-analytics__stat-value mb-0">
                {{ summary.usuarios_activos }}
              </h3>
            </div>
          </div>
        </b-card>
      </b-col>
    </b-row>

    <b-row>
      <b-col
        lg="8"
        class="mb-2"
      >
        <b-card
          class="balance-analytics__chart-card"
        >
          <template #header>
            <div class="d-flex flex-wrap align-items-center justify-content-between w-100">
              <h4 class="balance-analytics__card-title mb-0 d-flex align-items-center">
                <feather-icon
                  :icon="timeseriesMode === 'day' ? 'CalendarIcon' : 'BarChart2Icon'"
                  size="18"
                  class="mr-50 text-primary"
                />
                {{ timeseriesTitle }}
              </h4>
              <b-button
                v-if="timeseriesMode === 'day'"
                size="sm"
                variant="outline-primary"
                @click="exitMonthDrilldown"
              >
                <feather-icon
                  icon="ArrowLeftIcon"
                  size="14"
                  class="mr-50"
                />
                Volver a meses
              </b-button>
            </div>
          </template>
          <p
            v-if="timeseriesMode === 'month'"
            class="balance-analytics__chart-hint small mb-1"
          >
            Haz clic en un mes para ver el detalle por día.
          </p>
          <vue-apex-charts
            v-if="timeseriesCategories.length"
            :key="'timeseries-' + chartThemeKey + '-' + timeseriesMode"
            type="bar"
            height="320"
            :options="timeseriesOptions"
            :series="timeseriesSeries"
          />
          <p
            v-else
            class="balance-analytics__empty mb-0 text-center my-3"
          >
            Sin datos para el período seleccionado.
          </p>
        </b-card>
      </b-col>
      <b-col
        lg="4"
        class="mb-2"
      >
        <b-card class="balance-analytics__chart-card">
          <template #header>
            <h4 class="balance-analytics__card-title mb-0 d-flex align-items-center">
              <feather-icon
                icon="MapPinIcon"
                size="18"
                class="mr-50 text-warning"
              />
              Por valle
            </h4>
          </template>
          <vue-apex-charts
            v-if="byValleLabels.length"
            :key="'valle-' + chartThemeKey"
            type="donut"
            height="320"
            :options="byValleOptions"
            :series="byValleSeries"
          />
          <p
            v-else
            class="balance-analytics__empty mb-0 text-center my-3"
          >
            Sin datos para el período seleccionado.
          </p>
        </b-card>
      </b-col>
    </b-row>

    <b-row>
      <b-col
        lg="6"
        class="mb-2"
      >
        <b-card class="balance-analytics__chart-card">
          <template #header>
            <h4 class="balance-analytics__card-title mb-0 d-flex align-items-center">
              <feather-icon
                icon="LayersIcon"
                size="18"
                class="mr-50 text-success"
              />
              Por proceso
            </h4>
          </template>
          <vue-apex-charts
            v-if="byProcesoLabels.length"
            :key="'proceso-' + chartThemeKey"
            type="bar"
            height="320"
            :options="byProcesoOptions"
            :series="byProcesoSeries"
          />
          <p
            v-else
            class="balance-analytics__empty mb-0 text-center my-3"
          >
            Sin datos para el período seleccionado.
          </p>
        </b-card>
      </b-col>
      <b-col
        lg="6"
        class="mb-2"
      >
        <b-card class="balance-analytics__chart-card">
          <template #header>
            <h4 class="balance-analytics__card-title mb-0 d-flex align-items-center">
              <feather-icon
                icon="AwardIcon"
                size="18"
                class="mr-50 text-primary"
              />
              Top usuarios
            </h4>
          </template>
          <vue-apex-charts
            v-if="byUserLabels.length"
            :key="'users-' + chartThemeKey"
            type="bar"
            height="320"
            :options="byUserOptions"
            :series="byUserSeries"
          />
          <p
            v-else
            class="balance-analytics__empty mb-0 text-center my-3"
          >
            Sin datos para el período seleccionado.
          </p>
        </b-card>
      </b-col>
    </b-row>
      </b-overlay>
    </div>
  </div>
</template>

<script>
import {
  BCard,
  BRow,
  BCol,
  BFormInput,
  BFormSelect,
  BButton,
  BOverlay,
  BSpinner,
  BAvatar,
} from 'bootstrap-vue'
import VueApexCharts from 'vue-apexcharts'
import { $themeColors } from '@themeConfig'
import useAppConfig from '@core/app-config/useAppConfig'

function defaultFromDate() {
  const date = new Date()
  date.setMonth(date.getMonth() - 6)
  return date.toISOString().slice(0, 10)
}

function defaultToDate() {
  return new Date().toISOString().slice(0, 10)
}

export default {
  components: {
    BCard,
    BRow,
    BCol,
    BFormInput,
    BFormSelect,
    BButton,
    BOverlay,
    BSpinner,
    BAvatar,
    VueApexCharts,
  },
  data() {
    return {
      loading: false,
      filters: {
        from: defaultFromDate(),
        to: defaultToDate(),
        valle_id: '',
        proceso_id: '',
        user_id: '',
        metric: 'imported',
        group_by: 'month',
      },
      filterOptions: {
        users: [],
        valles: [],
        procesos: [],
      },
      summary: {
        sessions_imported: 0,
        balances_corridos: 0,
        balances_guardados: 0,
        usuarios_activos: 0,
      },
      byUser: [],
      byValle: [],
      byProceso: [],
      timeseries: [],
      cachedAt: null,
      timeseriesMode: 'month',
      selectedMonth: null,
      savedRange: null,
      metricOptions: [
        { value: 'imported', text: 'Sesiones importadas' },
        { value: 'correr', text: 'Balances corridos' },
      ],
    }
  },
  computed: {
    isDark() {
      const { skin } = useAppConfig()
      return skin.value === 'dark'
    },
    chartThemeKey() {
      return this.isDark ? 'dark' : 'light'
    },
    chartTextColor() {
      return this.isDark ? '#d0d2d6' : '#5e5873'
    },
    chartMutedColor() {
      return this.isDark ? '#b4b7bd' : '#6e6b7b'
    },
    chartGridColor() {
      return this.isDark ? '#3b4253' : '#ebe9f1'
    },
    apexBaseOptions() {
      return {
        chart: {
          foreColor: this.chartTextColor,
          background: 'transparent',
        },
        theme: {
          mode: this.isDark ? 'dark' : 'light',
        },
        grid: {
          borderColor: this.chartGridColor,
        },
        tooltip: {
          theme: this.isDark ? 'dark' : 'light',
          fillSeriesColor: false,
        },
      }
    },
    valleNameById() {
      const map = {}
      this.filterOptions.valles.forEach(valle => {
        map[valle.id] = valle.nombre
      })
      return map
    },
    availableProcesos() {
      const procesos = this.filterOptions.procesos || []
      if (!this.filters.valle_id) return procesos
      return procesos.filter(proceso => String(proceso.valle_id) === String(this.filters.valle_id))
    },
    procesoOptions() {
      const options = this.availableProcesos.map(proceso => {
        const valleNombre = this.valleNameById[proceso.valle_id]
        const suffix = !this.filters.valle_id && valleNombre ? ` (${valleNombre})` : ''
        return {
          value: String(proceso.id),
          text: `${proceso.nombre}${suffix}`,
        }
      })

      return [
        { value: '', text: 'Todos los procesos' },
        ...options,
      ]
    },
    valleOptions() {
      return [
        { value: '', text: 'Todos los valles' },
        ...this.filterOptions.valles.map(valle => ({
          value: String(valle.id),
          text: valle.nombre,
        })),
      ]
    },
    userOptions() {
      return [
        { value: '', text: 'Todos los usuarios' },
        ...this.filterOptions.users.map(user => ({
          value: String(user.id),
          text: user.nombre,
        })),
      ]
    },
    queryParams() {
      const params = {
        from: this.filters.from,
        to: this.filters.to,
        metric: this.filters.metric,
        group_by: this.filters.group_by,
      }
      if (this.filters.valle_id) params.valle_id = this.filters.valle_id
      if (this.filters.proceso_id) params.proceso_id = this.filters.proceso_id
      if (this.filters.user_id) params.user_id = this.filters.user_id
      return params
    },
    byUserLabels() {
      return this.byUser.map(item => item.nombre)
    },
    byUserSeries() {
      return [{
        name: this.filters.metric === 'correr' ? 'Corridas' : 'Importaciones',
        data: this.byUser.map(item => item.total),
      }]
    },
    byUserOptions() {
      return {
        ...this.apexBaseOptions,
        chart: {
          ...this.apexBaseOptions.chart,
          toolbar: { show: false },
        },
        plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
        colors: [$themeColors.primary],
        xaxis: {
          categories: this.byUserLabels,
          labels: { style: { colors: this.chartTextColor } },
          axisBorder: { color: this.chartGridColor },
          axisTicks: { color: this.chartGridColor },
        },
        yaxis: {
          labels: { style: { colors: this.chartTextColor } },
        },
        dataLabels: { enabled: false },
      }
    },
    byValleLabels() {
      return this.byValle.map(item => item.nombre)
    },
    byValleSeries() {
      return this.byValle.map(item => item.total)
    },
    byValleOptions() {
      return {
        ...this.apexBaseOptions,
        chart: {
          ...this.apexBaseOptions.chart,
          toolbar: { show: false },
        },
        labels: this.byValleLabels,
        legend: {
          position: 'bottom',
          labels: { colors: this.chartTextColor },
        },
        dataLabels: {
          enabled: true,
          style: {
            fontSize: '13px',
            fontWeight: 600,
            colors: ['#fff'],
          },
          dropShadow: {
            enabled: true,
            top: 1,
            left: 1,
            blur: 2,
            color: '#000',
            opacity: 0.85,
          },
        },
        colors: [$themeColors.primary, $themeColors.warning, $themeColors.success, $themeColors.info, $themeColors.danger],
        tooltip: {
          theme: this.isDark ? 'dark' : 'light',
          fillSeriesColor: false,
          style: {
            fontSize: '13px',
          },
        },
      }
    },
    byProcesoLabels() {
      return this.byProceso.map(item => item.label || item.nombre)
    },
    byProcesoSeries() {
      return [{
        name: this.filters.metric === 'correr' ? 'Corridas' : 'Importaciones',
        data: this.byProceso.map(item => item.total),
      }]
    },
    byProcesoOptions() {
      return {
        ...this.apexBaseOptions,
        chart: {
          ...this.apexBaseOptions.chart,
          toolbar: { show: false },
        },
        plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
        colors: [$themeColors.success],
        xaxis: {
          categories: this.byProcesoLabels,
          labels: { style: { colors: this.chartTextColor } },
          axisBorder: { color: this.chartGridColor },
          axisTicks: { color: this.chartGridColor },
        },
        yaxis: {
          labels: {
            style: { colors: this.chartTextColor },
            maxWidth: 160,
          },
        },
        dataLabels: { enabled: false },
      }
    },
    timeseriesTitle() {
      if (this.timeseriesMode === 'day' && this.selectedMonth) {
        return `Detalle diario — ${this.selectedMonth.label}`
      }
      return 'Evolución mensual'
    },
    timeseriesCategories() {
      return this.timeseries.map(item => item.period_label || item.period)
    },
    timeseriesSeries() {
      return [{
        name: this.filters.metric === 'correr' ? 'Corridas' : 'Importaciones',
        data: this.timeseries.map(item => item.total),
      }]
    },
    timeseriesOptions() {
      const self = this
      return {
        ...this.apexBaseOptions,
        plotOptions: {
          bar: {
            borderRadius: 4,
            columnWidth: '55%',
          },
        },
        chart: {
          ...this.apexBaseOptions.chart,
          toolbar: { show: false },
          events: {
            dataPointSelection(event, chartContext, config) {
              if (self.timeseriesMode === 'month') {
                self.drillIntoMonth(config.dataPointIndex)
              }
            },
          },
        },
        states: {
          active: {
            filter: { type: 'none' },
          },
        },
        colors: [$themeColors.primary],
        xaxis: {
          categories: this.timeseriesCategories,
          labels: {
            style: { colors: this.chartTextColor },
            rotate: -35,
            hideOverlappingLabels: true,
          },
          axisBorder: { color: this.chartGridColor },
          axisTicks: { color: this.chartGridColor },
        },
        yaxis: {
          labels: { style: { colors: this.chartTextColor } },
        },
        dataLabels: { enabled: false },
      }
    },
  },
  mounted() {
    this.loadFilterOptions()
    this.loadAll()
  },
  methods: {
    loadFilterOptions() {
      this.$http.get('/api/analytics/filter-options', { params: { refresh: 1 } })
        .then(response => {
          this.filterOptions = {
            users: response.data.users || [],
            valles: response.data.valles || [],
            procesos: response.data.procesos || [],
          }
        })
        .catch(() => {})
    },
    applyDashboardPayload(data) {
      this.summary = data.summary
      this.byUser = data.by_user
      this.byValle = data.by_valle
      this.byProceso = data.by_proceso || []
      this.timeseries = data.timeseries
      this.cachedAt = data.cached_at
    },
    onValleChange() {
      if (this.filters.proceso_id) {
        const isValid = this.availableProcesos.some(
          proceso => String(proceso.id) === String(this.filters.proceso_id),
        )
        if (!isValid) this.filters.proceso_id = ''
      }
      this.loadAll()
    },
    onProcesoChange() {
      this.$nextTick(() => {
        this.loadAll()
      })
    },
    onDateChange() {
      if (this.timeseriesMode === 'day') {
        this.timeseriesMode = 'month'
        this.filters.group_by = 'month'
        this.selectedMonth = null
        this.savedRange = null
      }
      this.loadAll()
    },
    drillIntoMonth(index) {
      const point = this.timeseries[index]
      if (!point || !point.period || this.timeseriesMode !== 'month') return

      const match = point.period.match(/^(\d{4})-(\d{2})$/)
      if (!match) return

      const year = parseInt(match[1], 10)
      const month = parseInt(match[2], 10)
      const from = `${match[1]}-${match[2]}-01`
      const lastDay = new Date(year, month, 0).getDate()
      const to = `${match[1]}-${match[2]}-${String(lastDay).padStart(2, '0')}`

      this.savedRange = {
        from: this.filters.from,
        to: this.filters.to,
        group_by: this.filters.group_by,
      }
      this.selectedMonth = {
        period: point.period,
        label: point.period_label || point.period,
        from,
        to,
      }
      this.timeseriesMode = 'day'
      this.filters.from = from
      this.filters.to = to
      this.filters.group_by = 'day'
      this.loadAll(true)
    },
    exitMonthDrilldown() {
      if (this.savedRange) {
        this.filters.from = this.savedRange.from
        this.filters.to = this.savedRange.to
        this.filters.group_by = this.savedRange.group_by || 'month'
      } else {
        this.filters.group_by = 'month'
      }
      this.timeseriesMode = 'month'
      this.selectedMonth = null
      this.savedRange = null
      this.loadAll(true)
    },
    loadAll(forceRefresh = false) {
      this.loading = true
      const params = { ...this.queryParams }
      if (forceRefresh) params.refresh = 1

      this.$http.get('/api/analytics/dashboard', { params })
        .then(response => {
          this.applyDashboardPayload(response.data)
        })
        .finally(() => {
          this.loading = false
        })
    },
    loadCharts() {
      if (this.timeseriesMode === 'day') {
        this.exitMonthDrilldown()
        return
      }
      this.loadAll()
    },
  },
}
</script>

<style lang="scss">
.balance-analytics {
  &__title {
    color: #5e5873;
    font-weight: 600;
  }

  &__subtitle,
  &__label,
  &__stat-label,
  &__empty {
    color: #6e6b7b;
  }

  &__stat-value {
    color: #5e5873;
    font-weight: 600;
  }

  &__label {
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  &__overlay {
    min-height: 420px;
  }

  &__loading-text {
    color: #6e6b7b;
    font-size: 0.875rem;
  }

  &__card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #5e5873;
  }

  &__chart-hint {
    color: #6e6b7b;
  }

  .apexcharts-tooltip {
    color: #5e5873;

    .apexcharts-tooltip-title,
    .apexcharts-tooltip-text,
    .apexcharts-tooltip-text-label,
    .apexcharts-tooltip-text-value,
    .apexcharts-tooltip-series-group,
    span,
    span.apexcharts-tooltip-text-y-label,
    span.apexcharts-tooltip-text-y-value {
      color: #5e5873 !important;
      fill: #5e5873 !important;
    }

    &.apexcharts-theme-dark {
      color: #fff;

      .apexcharts-tooltip-title,
      .apexcharts-tooltip-text,
      .apexcharts-tooltip-text-label,
      .apexcharts-tooltip-text-value,
      span {
        color: #fff !important;
        fill: #fff !important;
      }
    }
  }
}

.dark-layout {
  .balance-analytics {
    &__title,
    &__stat-value {
      color: #d0d2d6;
    }

    &__subtitle,
    &__label,
    &__stat-label,
    &__empty,
    &__loading-text,
    &__chart-hint {
      color: #b4b7bd;
    }

    &__card-title {
      color: #d0d2d6;
    }
  }

  .balance-analytics__chart-card {
    .card-title,
    .card-header .card-title {
      color: #d0d2d6;
    }
  }
}
</style>
