<template>
  <div>
    <p class="text-muted mb-1">
      {{ $t('balances.sankey.explain') }}
    </p>

    <div class="flow-kpis mb-1">
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

    <div class="d-flex flex-wrap align-items-center mb-50">
      <span
        v-for="tipo in tipos"
        :key="tipo"
        class="flow-legend mr-2"
      >
        <span
          class="flow-swatch"
          :style="{ background: palette[tipo] }"
        />
        {{ $t(`balances.sankey.legend.${tipo}`) }}
      </span>
      <b-form-checkbox
        v-model="animar"
        switch
        class="ml-auto"
      >
        {{ $t('balances.sankey.animate') }}
      </b-form-checkbox>
    </div>

    <p
      v-if="!graph.links.length"
      class="text-muted mb-0"
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
        ref="chart"
        class="balance-sankey__chart"
      />
    </div>

    <div
      v-if="graph.recirculaciones.length"
      class="mt-2"
    >
      <h6 class="mb-25">
        {{ $t('balances.sankey.recirculations') }}
      </h6>
      <small class="text-muted d-block mb-50">{{ $t('balances.sankey.recirculationsHint') }}</small>
      <b-table
        :items="graph.recirculaciones"
        :fields="recirculacionFields"
        small
        responsive
        class="mb-0"
      />
    </div>
  </div>
</template>

<script>
import { BTable, BFormCheckbox } from 'bootstrap-vue'
import useAppConfig from '@core/app-config/useAppConfig'

const formatTms = v => Number(v).toLocaleString('es-CL', { maximumFractionDigits: 0 })
const CURVENESS = 0.5
// Role colors, validated for color-vision deficiency on light and dark surfaces; stock stays neutral gray.
const PALETTES = {
  light: { entrada: '#eb6834', proceso: '#2a78d6', salida: '#1baf7a', stock: '#8b8a85', text: '#5e5873', muted: '#6e6b7b' },
  dark: { entrada: '#d95926', proceso: '#3987e5', salida: '#199e70', stock: '#8f8e88', text: '#d0d2d6', muted: '#b4b7bd' },
}

export default {
  components: { BTable, BFormCheckbox },
  props: {
    graph: {
      type: Object,
      required: true,
    },
  },
  data() {
    const reduceMotion = typeof window !== 'undefined' && window.matchMedia
      && window.matchMedia('(prefers-reduced-motion: reduce)').matches
    return {
      chart: null,
      particles: [],
      animar: !reduceMotion,
      tipos: ['entrada', 'proceso', 'salida', 'stock'],
    }
  },
  computed: {
    isDark() {
      return useAppConfig().skin.value === 'dark'
    },
    palette() {
      return PALETTES[this.isDark ? 'dark' : 'light']
    },
    kpis() {
      const t = this.graph.totales
      const cierre = t.entradas + t.stockConsumo - t.salidas - t.stockAcumulacion
      return [
        { key: 'in', color: this.palette.entrada, value: `${formatTms(t.entradas)} t`, label: this.$t('balances.sankey.kpi.entradas') },
        { key: 'out', color: this.palette.salida, value: `${formatTms(t.salidas)} t`, label: this.$t('balances.sankey.kpi.salidas') },
        {
          key: 'stock',
          color: this.palette.stock,
          value: `−${formatTms(t.stockConsumo)} / +${formatTms(t.stockAcumulacion)} t`,
          label: this.$t('balances.sankey.kpi.stock'),
        },
        { key: 'cierre', color: 'transparent', value: `${formatTms(cierre)} t`, label: this.$t('balances.sankey.kpi.cierre') },
      ]
    },
    recirculacionFields() {
      return [
        { key: 'flujo', label: this.$t('balances.sankey.flow') },
        { key: 'source', label: this.$t('balances.sankey.from') },
        { key: 'target', label: this.$t('balances.sankey.to') },
        { key: 'value', label: 'TMS', formatter: formatTms, class: 'text-right' },
      ]
    },
  },
  watch: {
    isDark() { this.render() },
    graph() { this.render() },
  },
  async mounted() {
    // Loaded on demand so the Sankey code only ships to users who open the diagram.
    const [core] = await Promise.all([
      import(/* webpackChunkName: "echarts-sankey" */ 'echarts/lib/echarts'),
      import(/* webpackChunkName: "echarts-sankey" */ 'echarts/lib/chart/sankey'),
      import(/* webpackChunkName: "echarts-sankey" */ 'echarts/lib/component/tooltip'),
    ])
    if (this.isDestroyed) return
    this.chart = (core.default || core).init(this.$refs.chart)
    window.addEventListener('resize', this.resize)
    this.render()
  },
  beforeDestroy() {
    this.isDestroyed = true
    window.removeEventListener('resize', this.resize)
    if (this.chart) this.chart.dispose()
  },
  methods: {
    resize() {
      if (!this.chart) return
      this.chart.resize()
      this.updateParticles()
    },
    tooltip(p) {
      const t = this.graph.totales
      if (p.dataType === 'edge') {
        const total = t.entradas + t.stockConsumo
        const pct = total ? ` · ${((p.data.value / total) * 100).toFixed(1)}% ${this.$t('balances.sankey.ofInput')}` : ''
        return `<b>${p.data.flujo}</b><br>${p.data.source} → ${p.data.target}<br>${formatTms(p.data.value)} t${pct}`
      }
      return `<b>${p.name}</b><br>${this.$t(`balances.sankey.legend.${p.data.tipo}`)} · ${formatTms(p.value)} t`
    },
    render() {
      if (!this.chart) return
      const c = this.palette
      this.chart.setOption({
        tooltip: { trigger: 'item', formatter: this.tooltip },
        series: [{
          type: 'sankey',
          left: 8,
          right: 230,
          top: 8,
          bottom: 24,
          nodeWidth: 14,
          nodeGap: 22,
          draggable: false, // the particle overlay follows the computed layout, not dragged nodes
          focusNodeAdjacency: 'allEdges',
          data: this.graph.nodes.map(n => ({
            name: n.name,
            value: n.value,
            tipo: n.tipo,
            itemStyle: { color: c[n.tipo], borderWidth: 0 },
          })),
          links: this.graph.links.map(l => ({
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
          lineStyle: { opacity: this.isDark ? 0.4 : 0.3, curveness: CURVENESS },
          emphasis: { lineStyle: { opacity: 0.6 } },
        }],
      }, true)
      this.updateParticles()
    },
    // Rebuilds the center line of every band from echarts' own Sankey layout (same math as
    // echarts 4.8 SankeyView), so animated dots can run along each flow.
    updateParticles() {
      const series = this.chart && this.chart.getModel().getSeriesByIndex(0)
      if (!series || !series.layoutInfo) { this.particles = []; return }
      const { x: ox, y: oy } = series.layoutInfo
      const edgeData = series.getData('edge')
      const particles = []
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
        particles.push({
          d: `M${x1},${y1} C${cx1},${y1} ${cx2},${y2} ${x2},${y2}`,
          color: this.palette[tipo === 'interno' ? 'proceso' : tipo],
          width: Math.min(Math.max(e.dy * 0.2, 2), 5),
        })
      })
      this.particles = particles
    },
  },
}
</script>

<style lang="scss" scoped>
.balance-sankey {
  position: relative;
  width: 100%;
  height: 62vh;
  min-height: 420px;
}
.balance-sankey__chart {
  position: relative;
  width: 100%;
  height: 100%;
}
.balance-sankey__particles {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  overflow: visible;

  path {
    fill: none;
    stroke-linecap: round;
    stroke-dasharray: 0.1 21.9;
    opacity: 0.85;
    animation: flow-dots 0.9s linear infinite;
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
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.flow-kpi__label {
  font-size: 0.8rem;
  opacity: 0.75;
}
.flow-swatch {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 2px;
  margin-top: 0.35rem;
  flex-shrink: 0;
}
.flow-legend {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;

  .flow-swatch { margin-top: 0; }
}
</style>
