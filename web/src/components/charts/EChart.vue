<script setup lang="ts">
// ECharts 6, tree-shaken: only the charts and components the app draws. Colors come from the CSS tokens and are
// re-read when the theme flips, so the same option renders light and dark.
import { BarChart, LineChart, PieChart } from 'echarts/charts'
import { GridComponent, LegendComponent, MarkLineComponent, TooltipComponent } from 'echarts/components'
import { init, use, type EChartsCoreOption, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { onBeforeUnmount, onMounted, ref, shallowRef, watchEffect } from 'vue'

use([BarChart, LineChart, PieChart, GridComponent, LegendComponent, MarkLineComponent, TooltipComponent, CanvasRenderer])

export interface Tokens {
  ink: string
  inkMuted: string
  inkFaint: string
  line: string
  surface: string
  primary: string
  danger: string
  dangerSoft: string
  warn: string
  ok: string
  font: string
}

const props = defineProps<{
  titulo: string
  /** One sentence that says what the chart shows; also the accessible description. */
  resumen: string
  option: (t: Tokens) => EChartsCoreOption
  /** CSS height of the drawing. */
  alto?: string
  /** Sparkline: title and summary only for screen readers. */
  compacto?: boolean
}>()
/** Index of the data point the user clicked (e.g. analytics drill-down). */
const emit = defineEmits<{ select: [index: number] }>()

const el = ref<HTMLDivElement>()
const chart = shallowRef<ECharts>()
const tokens = ref<Tokens>(leer())

// Read on the chart's own element, so a subtree forced to the light theme (.light, the Excel export) gets light colors.
function leer(): Tokens {
  const css = getComputedStyle(el.value ?? document.documentElement)
  const v = (n: string) => css.getPropertyValue(n).trim()
  return {
    ink: v('--ink'), inkMuted: v('--ink-muted'), inkFaint: v('--ink-faint'), line: v('--line'), surface: v('--surface'),
    primary: v('--primary'), danger: v('--danger'), dangerSoft: v('--danger-soft'), warn: v('--warn'), ok: v('--ok'),
    font: '"Work Sans", system-ui, sans-serif',
  }
}

// theme.ts toggles html.dark; follow it.
const tema = new MutationObserver(() => (tokens.value = leer()))
let size: ResizeObserver | undefined
// Drawn = the last setOption finished rendering, animation included (exports wait for it).
let pintado = false
const esperando: (() => void)[] = []
onMounted(() => {
  tokens.value = leer()
  chart.value = init(el.value!, null, { renderer: 'canvas' })
  chart.value.on('click', p => emit('select', p.dataIndex))
  chart.value.on('finished', () => {
    pintado = true
    esperando.splice(0).forEach(f => f())
  })
  tema.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
  size = new ResizeObserver(() => chart.value?.resize())
  size.observe(el.value!)
})
watchEffect(() => {
  const t = tokens.value
  pintado = false
  chart.value?.setOption({ textStyle: { fontFamily: t.font, color: t.inkMuted }, animationDuration: 300, ...props.option(t) }, true)
})
onBeforeUnmount(() => {
  esperando.splice(0).forEach(f => f()) // nothing left to wait for
  tema.disconnect()
  size?.disconnect()
  chart.value?.dispose()
})

/** PNG data URL of the chart at 2× on the current surface color (stable API, reused by the PDF report). */
function exportPng(): string {
  return chart.value?.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: tokens.value.surface }) ?? ''
}
/** Resolves when the chart is fully drawn (initial animation over), so exportPng captures the final frame. */
const listo = (): Promise<void> => (pintado ? Promise.resolve() : new Promise(r => esperando.push(r)))
defineExpose({ exportPng, listo })
</script>

<template>
  <figure class="min-w-0">
    <figcaption :class="compacto ? 'sr-only' : 'mb-2'">
      <h3 class="text-sm font-semibold">{{ titulo }}</h3>
      <p class="text-xs text-ink-muted">{{ resumen }}</p>
    </figcaption>
    <div ref="el" role="img" :aria-label="`${titulo}. ${resumen}`" :style="{ height: alto ?? '16rem' }" />
  </figure>
</template>
