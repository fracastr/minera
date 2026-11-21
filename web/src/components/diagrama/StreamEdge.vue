<script setup lang="ts">
import { computed, inject } from 'vue'
import { DIAGRAM_CTX } from './context'
import { arrowAt, pathOf } from './layout'

const props = defineProps<{ id: string; data: { flujo: number; primary: boolean; stock: boolean } }>()
const ctx = inject(DIAGRAM_CTX)!

const g = computed(() => ctx.geom[props.id])
const chip = computed(() => ctx.chips[props.data.flujo])
const d = computed(() => (g.value ? pathOf(g.value.points) : ''))
const arrow = computed(() => (g.value ? arrowAt(g.value.points) : ''))
const foco = computed(() => ctx.foco[props.id])
const sel = computed(() => ctx.flujoSel === props.data.flujo)
const hot = computed(() => sel.value || ctx.flujoHover === props.data.flujo)
const cls = computed(() => [
  `t-${chip.value?.tono ?? 'normal'}`,
  foco.value ? `f-${foco.value}` : '',
  { sel: sel.value, hot: hot.value, stock: props.data.stock },
])
</script>

<template>
  <g v-if="g && chip" class="fs-edge" :class="cls" @pointerenter="ctx.hover(data.flujo, $event)" @pointermove="ctx.hover(data.flujo, $event)" @pointerleave="ctx.hover(null)" @click.stop="ctx.abrirFlujo(data.flujo)">
    <path class="fs-hit" data-export="skip" :d="d" />
    <path class="fs-line" :d="d" />
    <path class="fs-arrow" :d="arrow" />
    <g
      v-if="data.primary && g.label"
      class="fs-chip" role="button" tabindex="0" :aria-label="chip.aria"
      :transform="`translate(${g.label.x},${g.label.y})`"
      @keydown.enter.prevent="ctx.abrirFlujo(data.flujo)" @keydown.space.prevent="ctx.abrirFlujo(data.flujo)"
    >
      <rect class="fs-chip-bg" :x="(g.label.width - Math.min(chip.ancho, g.label.width)) / 2" :width="Math.min(chip.ancho, g.label.width)" :height="g.label.height" rx="4" />
      <text class="fs-chip-name" :x="g.label.width / 2" y="12.5" text-anchor="middle">{{ chip.nombre }}</text>
      <text class="fs-chip-vals" :x="g.label.width / 2" y="26" text-anchor="middle">
        <template v-for="([k, v], i) in chip.partes" :key="k">
          <tspan v-if="i" class="fs-chip-sep">{{ "\u00a0·\u00a0" }}</tspan><tspan class="fs-chip-key">{{ `${k}\u00a0` }}</tspan><tspan>{{ v }}</tspan>
        </template>
      </text>
    </g>
  </g>
</template>
