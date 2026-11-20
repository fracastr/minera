<script setup>
// Sankey of the balance flows (port of resources/js/src/views/balances/BalanceSankey.vue).
import { useTheme } from 'vuetify'

const props = defineProps({
  graph: { type: Object, required: true },
})

const { t } = useI18n()
const vuetifyTheme = useTheme()

const formatTms = v => Number(v).toLocaleString('es-CL', { maximumFractionDigits: 0 })
const CURVENESS = 0.5

// Role colors, validated for color-vision deficiency on light and dark surfaces; stock stays neutral gray.
const PALETTES = {
  light: { entrada: '#eb6834', proceso: '#2a78d6', salida: '#1baf7a', stock: '#8b8a85', text: '#5e5873', muted: '#6e6b7b' },
  dark: { entrada: '#d95926', proceso: '#3987e5', salida: '#199e70', stock: '#8f8e88', text: '#d0d2d6', muted: '#b4b7bd' },
}

const tipos = ['entrada', 'proceso', 'salida', 'stock']
const chartEl = ref()
const particles = ref([])
const animar = ref(!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches)
const isDark = computed(() => vuetifyTheme.global.current.value.dark)
const palette = computed(() => PALETTES[isDark.value ? 'dark' : 'light'])
let chart = null
let disposed = false

const kpis = computed(() => {
  const tot = props.graph.totales
  const cierre = tot.entradas + tot.stockConsumo - tot.salidas - tot.stockAcumulacion
  const c = palette.value

  return [
    { key: 'in', color: c.entrada, value: `${formatTms(tot.entradas)} t`, label: t('balances.sankey.kpi.entradas') },
    { key: 'out', color: c.salida, value: `${formatTms(tot.salidas)} t`, label: t('balances.sankey.kpi.salidas') },
    {
      key: 'stock',
      color: c.stock,
      value: `−${formatTms(tot.stockConsumo)} / +${formatTms(tot.stockAcumulacion)} t`,
      label: t('balances.sankey.kpi.stock'),
    },
    { key: 'cierre', color: 'transparent', value: `${formatTms(cierre)} t`, label: t('balances.sankey.kpi.cierre') },
  ]
})

const recirculacionHeaders = computed(() => [
  { key: 'flujo', title: t('balances.sankey.flow') },
  { key: 'source', title: t('balances.sankey.from') },
  { key: 'target', title: t('balances.sankey.to') },
  { key: 'value', title: 'TMS', align: 'end', value: item => formatTms(item.value) },
])

function tooltip(p) {
  const tot = props.graph.totales
  if (p.dataType === 'edge') {
    const total = tot.entradas + tot.stockConsumo
    const pct = total ? ` · ${((p.data.value / total) * 100).toFixed(1)}% ${t('balances.sankey.ofInput')}` : ''

    return `<b>${p.data.flujo}</b><br>${p.data.source} → ${p.data.target}<br>${formatTms(p.data.value)} t${pct}`
  }

  return `<b>${p.name}</b><br>${t(`balances.sankey.legend.${p.data.tipo}`)} · ${formatTms(p.value)} t`
}

// Rebuilds the center line of every band from echarts' own Sankey layout (same math as the horizontal,
// non-dragged branch of echarts 6 SankeyView.render), so animated dots can run along each flow.
function updateParticles() {
  const series = chart?.getModel().getSeriesByIndex(0)
  if (!series?.layoutInfo) {
    particles.value = []

    return
  }
  const { x: ox, y: oy } = series.layoutInfo
  const edgeData = series.getData('edge')
  const out = []

  series.getGraph().eachEdge(edge => {
    const n1 = edge.node1.getLayout()
    const n2 = edge.node2.getLayout()
    const e = edge.getLayout()
    const x1 = ox + n1.x + n1.dx
    const y1 = oy + n1.y + e.sy + e.dy / 2
    const x2 = ox + n2.x
    const y2 = oy + n2.y + e.ty + e.dy / 2
    const cx1 = x1 * (1 - CURVENESS) + x2 * CURVENESS
    const cx2 = x1 * CURVENESS + x2 * (1 - CURVENESS)
    const { tipo } = edgeData.getRawDataItem(edge.dataIndex)

    out.push({
      d: `M${x1},${y1} C${cx1},${y1} ${cx2},${y2} ${x2},${y2}`,
      color: palette.value[tipo === 'interno' ? 'proceso' : tipo],
      width: Math.min(Math.max(e.dy * 0.2, 2), 5),
    })
  })
  particles.value = out
}

function render() {
  if (!chart)
    return
  const c = palette.value

  chart.setOption({
    tooltip: { trigger: 'item', formatter: tooltip },
    series: [{
      type: 'sankey',
      left: 8,
      right: 230,
      top: 8,
      bottom: 24,
      nodeWidth: 14,
      nodeGap: 22,
      draggable: false, // the particle overlay follows the computed layout, not dragged nodes
      data: props.graph.nodes.map(n => ({
        name: n.name,
        value: n.value,
        tipo: n.tipo,
        itemStyle: { color: c[n.tipo], borderWidth: 0 },
      })),
      links: props.graph.links.map(l => ({
        ...l,
        lineStyle: { color: c[l.tipo === 'interno' ? 'proceso' : l.tipo] },
      })),
      label: {
        color: c.text,
        formatter: p => `{n|${p.name}}\n{v|${formatTms(p.value)} t}`,
        rich: {
          n: { color: c.text, fontSize: 12, fontWeight: 600, lineHeight: 16 },
          v: { color: c.muted, fontSize: 11, lineHeight: 14 },
        },
      },
      lineStyle: { opacity: isDark.value ? 0.4 : 0.3, curveness: CURVENESS },
      emphasis: { focus: 'adjacency', lineStyle: { opacity: 0.6 } },
    }],
  }, true)
  updateParticles()
}

function resize() {
  if (!chart)
    return
  chart.resize()
  updateParticles()
}

watch([isDark, () => props.graph], render)

onMounted(async () => {
  // Loaded on demand so the Sankey code only ships to users who open the diagram.
  const [echarts, { SankeyChart }, { TooltipComponent }, { CanvasRenderer }] = await Promise.all([
    import('echarts/core'),
    import('echarts/charts'),
    import('echarts/components'),
    import('echarts/renderers'),
  ])

  if (disposed)
    return
  echarts.use([SankeyChart, TooltipComponent, CanvasRenderer])
  chart = echarts.init(chartEl.value)
  window.addEventListener('resize', resize)
  render()
})

onBeforeUnmount(() => {
  disposed = true
  window.removeEventListener('resize', resize)
  chart?.dispose()
})
</script>

<template>
  <div>
    <p class="text-medium-emphasis mb-4">
      {{ $t('balances.sankey.explain') }}
    </p>

    <div class="flow-kpis mb-4">
      <div
        v-for="kpi in kpis"
        :key="kpi.key"
        class="flow-kpi"
      >
        <span
          class="flow-swatch"
          :style="{ background: kpi.color }"
        />
        <div>
          <div class="flow-kpi__value">
            {{ kpi.value }}
          </div>
          <div class="flow-kpi__label">
            {{ kpi.label }}
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap align-center gap-x-6 mb-2">
      <span
        v-for="tipo in tipos"
        :key="tipo"
        class="flow-legend"
      >
        <span
          class="flow-swatch"
          :style="{ background: palette[tipo] }"
        />
        {{ $t(`balances.sankey.legend.${tipo}`) }}
      </span>
      <VSwitch
        v-model="animar"
        :label="$t('balances.sankey.animate')"
        class="ms-auto"
        density="compact"
        hide-details
      />
    </div>

    <p
      v-if="!graph.links.length"
      class="text-medium-emphasis mb-0"
    >
      {{ $t('balances.sankey.empty') }}
    </p>
    <div
      v-show="graph.links.length"
      class="balance-sankey"
    >
      <!-- Particles sit under the translucent bands so node labels stay on top. -->
      <svg
        v-if="animar"
        class="balance-sankey__particles"
        aria-hidden="true"
      >
        <path
          v-for="(p, i) in particles"
          :key="i"
          :d="p.d"
          :stroke="p.color"
          :stroke-width="p.width"
        />
      </svg>
      <div
        ref="chartEl"
        class="balance-sankey__chart"
      />
    </div>

    <div
      v-if="graph.recirculaciones.length"
      class="mt-6"
    >
      <h6 class="text-h6 mb-1">
        {{ $t('balances.sankey.recirculations') }}
      </h6>
      <small class="text-medium-emphasis d-block mb-2">{{ $t('balances.sankey.recirculationsHint') }}</small>
      <VDataTable
        :headers="recirculacionHeaders"
        :items="graph.recirculaciones"
        :items-per-page="-1"
        density="compact"
        hide-default-footer
      />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.balance-sankey {
  position: relative;
  block-size: 62vh;
  inline-size: 100%;
  min-block-size: 420px;
}

.balance-sankey__chart {
  position: relative;
  block-size: 100%;
  inline-size: 100%;
}

.balance-sankey__particles {
  position: absolute;
  overflow: visible;
  block-size: 100%;
  inline-size: 100%;
  inset: 0;
  pointer-events: none;

  path {
    animation: flow-dots 0.9s linear infinite;
    fill: none;
    opacity: 0.85;
    stroke-dasharray: 0.1 21.9;
    stroke-linecap: round;
  }
}

@keyframes flow-dots {
  to { stroke-dashoffset: -22; }
}

@media (prefers-reduced-motion: reduce) {
  .balance-sankey__particles path { animation: none; }
}

.flow-kpis {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 2rem;
}

.flow-kpi {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
}

.flow-kpi__value {
  font-size: 1.1rem;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.flow-kpi__label {
  font-size: 0.8rem;
  opacity: 0.75;
}

.flow-swatch {
  display: inline-block;
  flex-shrink: 0;
  border-radius: 2px;
  block-size: 10px;
  inline-size: 10px;
  margin-block-start: 0.35rem;
}

.flow-legend {
  display: inline-flex;
  align-items: center;
  font-size: 0.85rem;
  gap: 0.4rem;

  .flow-swatch { margin-block-start: 0; }
}
</style>
