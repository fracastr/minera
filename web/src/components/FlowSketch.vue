<script setup lang="ts">
// Illustrative flowsheet (made-up values) in the notation the balance diagram will use: M measured, R reconciled, E error.
defineProps<{ tone: 'rail' | 'canvas' }>()

const nodes = [
  { x: 96, w: 112, label: 'Molienda' },
  { x: 280, w: 120, label: 'Sep. magnética' },
]
const streams = [
  { id: 'f1', d: 'M8 150 H96', label: 'Alimentación', x: 12, y: 140, values: 'M 1.234', vy: 168 },
  { id: 'f2', d: 'M208 150 H280', label: 'Descarga', x: 214, y: 140, values: 'R 1.712', vy: 168 },
  { id: 'f3', d: 'M400 140 H440 V62 H512', label: 'Concentrado', x: 446, y: 52, values: 'M 702 · R 698', vy: 80 },
  { id: 'f4', d: 'M400 160 H440 V238 H512', label: 'Relave', x: 446, y: 228, values: 'E 3,8 %', vy: 256, off: true },
  { id: 'f5', d: 'M340 172 V214 H152 V172', label: 'Recirculación', x: 200, y: 206, values: 'R 472', vy: 230, dashed: true },
]
</script>

<template>
  <svg
    viewBox="0 0 520 290"
    class="sketch w-full"
    :class="tone === 'rail' ? 'text-rail-muted [--sk-ink:var(--color-rail-ink)] [--sk-node:var(--color-rail-hover)] [--sk-warn:#f0a43a]' : 'text-ink-faint [--sk-ink:var(--color-ink)] [--sk-node:var(--color-surface)] [--sk-warn:var(--color-warn)]'"
    role="img"
    aria-label="Esquema de un balance: alimentación, molienda, separación magnética, concentrado, relave y recirculación"
  >
    <defs>
      <marker id="sk-arrow" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
        <path d="M0 0 L8 4 L0 8 z" fill="currentColor" />
      </marker>
    </defs>

    <g v-for="s in streams" :key="s.id">
      <path
        :d="s.d"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
        :stroke-dasharray="s.dashed ? '4 4' : undefined"
        :class="['stream', { 'text-(color:--sk-warn)': s.off }]"
        marker-end="url(#sk-arrow)"
      />
      <text :x="s.x" :y="s.y" class="fill-[var(--sk-ink)] text-[11px] font-medium">{{ s.label }}</text>
      <text :x="s.x" :y="s.vy" class="text-[10px] tabular-nums" :class="s.off ? 'fill-[var(--sk-warn)]' : 'fill-current'">{{ s.values }}</text>
    </g>

    <g v-for="n in nodes" :key="n.label">
      <rect :x="n.x" y="128" :width="n.w" height="44" rx="4" class="fill-[var(--sk-node)]" stroke="currentColor" stroke-width="1.5" />
      <text :x="n.x + n.w / 2" y="154" text-anchor="middle" class="fill-[var(--sk-ink)] text-[11.5px] font-medium">{{ n.label }}</text>
    </g>
  </svg>
</template>

<style scoped>
.stream {
  stroke-dashoffset: 0;
}
@media (prefers-reduced-motion: no-preference) {
  .sketch .stream:not([stroke-dasharray]) {
    stroke-dasharray: 400;
    animation: draw 1.4s var(--ease-out-expo) both;
  }
}
@keyframes draw {
  from {
    stroke-dashoffset: 400;
  }
}
</style>
