<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core'
import { computed, inject } from 'vue'
import type { TipoNodo } from '@/domain/flowsheet'
import { DIAGRAM_CTX } from './context'

const props = defineProps<{ id: string; data: { tipo: TipoNodo; width: number; height: number; flujo?: number } }>()
const ctx = inject(DIAGRAM_CTX)!

const v = computed(() => ctx.nodos[props.id])
const dim = computed(() => ctx.nodosAtenuados.has(props.id))
const ARROWS: Record<string, [number, number]> = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }
function onKey(e: KeyboardEvent) {
  const d = ARROWS[e.key]
  if (!d || !ctx.editable) return
  e.preventDefault()
  e.stopPropagation() // the canvas would pan otherwise
  const k = e.shiftKey ? 5 : 1
  ctx.mover(props.id, d[0] * k, d[1] * k)
}
const w = computed(() => props.data.width)
const h = computed(() => props.data.height)
// Stockpile glyph: a pile (triangle) on a base line.
const pila = computed(() => `M2,${h.value - 4} L${w.value / 2},4 L${w.value - 2},${h.value - 4} Z`)
</script>

<template>
  <div :style="{ width: `${w}px`, height: `${h}px` }" :class="{ 'fs-dim': dim }">
    <Handle type="target" :position="Position.Left" :connectable="false" class="fs-handle" />
    <svg
      v-if="data.tipo === 'unidad' && v"
      class="fs-node" :width="w" :height="h" :viewBox="`0 0 ${w} ${h}`"
      role="button" tabindex="0" :aria-label="v.aria" :aria-pressed="ctx.nodoSelId === id"
      @keydown="onKey"
      @click.stop="ctx.elegirNodo(id)" @keydown.enter.prevent="ctx.elegirNodo(id)" @keydown.space.prevent="ctx.elegirNodo(id)"
    >
      <rect class="fs-unit" x="0.75" y="0.75" :width="w - 1.5" :height="h - 1.5" rx="3" />
      <text class="fs-unit-name" :x="w / 2" :y="h / 2 - 3" text-anchor="middle">{{ v.label }}</text>
      <text class="fs-unit-delta" :class="`t-${v.deltaTono}`" :x="w / 2" :y="h / 2 + 12" text-anchor="middle">{{ v.delta }}</text>
    </svg>
    <svg v-else class="fs-node fs-term" :class="`fs-${data.tipo}`" :width="w" :height="h" :viewBox="`0 0 ${w} ${h}`" aria-hidden="true">
      <title>{{ data.tipo === 'stock' ? 'Inventario' : data.tipo === 'entrada' ? 'Entrada al sistema' : 'Salida del sistema' }}</title>
      <path v-if="data.tipo === 'stock'" class="fs-pile" :d="pila" />
      <template v-else>
        <circle class="fs-port" :cx="w / 2" :cy="h / 2" :r="w / 2 - 2" />
        <path class="fs-port-arrow" :d="`M${w / 2 - 3},${h / 2 - 5} L${w / 2 + 3},${h / 2} L${w / 2 - 3},${h / 2 + 5}`" />
      </template>
    </svg>
    <Handle type="source" :position="Position.Right" :connectable="false" class="fs-handle" />
  </div>
</template>
