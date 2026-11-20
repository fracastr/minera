<template>
  <div>
    <p
      v-if="!graph.links.length"
      class="text-muted mb-0"
    >
      {{ $t('balances.sankey.empty') }}
    </p>
    <div
      v-show="graph.links.length"
      ref="chart"
      class="balance-sankey"
    />
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
import { BTable } from 'bootstrap-vue'
import useAppConfig from '@core/app-config/useAppConfig'

const formatTms = v => Number(v).toLocaleString('es-CL', { maximumFractionDigits: 0 })

export default {
  components: { BTable },
  props: {
    graph: {
      type: Object,
      required: true,
    },
  },
  data() {
    return { chart: null }
  },
  computed: {
    isDark() {
      return useAppConfig().skin.value === 'dark'
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
      if (this.chart) this.chart.resize()
    },
    render() {
      if (!this.chart) return
      const text = this.isDark ? '#d0d2d6' : '#5e5873'
      const muted = this.isDark ? '#676d7d' : '#b9b9c3'
      this.chart.setOption({
        tooltip: {
          trigger: 'item',
          formatter: p => (p.dataType === 'edge'
            ? `${p.data.flujo}<br>${p.data.source} → ${p.data.target}: <b>${formatTms(p.data.value)}</b> TMS`
            : `${p.name}: <b>${formatTms(p.value)}</b> TMS`),
        },
        series: [{
          type: 'sankey',
          left: 8,
          right: 160,
          top: 16,
          bottom: 16,
          nodeGap: 14,
          draggable: true,
          focusNodeAdjacency: 'allEdges',
          data: this.graph.nodes.map(n => ({
            name: n.name,
            itemStyle: n.externo ? { color: muted, borderColor: muted } : { color: '#7367f0', borderColor: '#7367f0' },
          })),
          links: this.graph.links,
          label: { color: text, fontSize: 12 },
          lineStyle: { color: 'source', opacity: this.isDark ? 0.35 : 0.25, curveness: 0.5 },
        }],
      }, true)
      this.resize()
    },
  },
}
</script>

<style scoped>
.balance-sankey {
  width: 100%;
  height: 70vh;
  min-height: 420px;
}
</style>
