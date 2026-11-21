<script setup lang="ts">
import { X } from '@lucide/vue'
import { computed } from 'vue'
import { computeQuality } from '@/domain/reconciliationQuality'
import type { Sesion } from '@/domain/types'
import { fmt } from '@/lib/format'
import FlujosGrid from './FlujosGrid.vue'
import InventariosGrid from './InventariosGrid.vue'
import NodosGrid from './NodosGrid.vue'

const props = defineProps<{ sesion: Sesion; editable: boolean; locked: boolean }>()
const nodo = defineModel<number | null>('nodo', { required: true })
const emit = defineEmits<{ edit: [] }>()

const q = computed(() => computeQuality(props.sesion))
const kpis = computed(() => {
  const s = q.value.summary
  const max = s.mayorAjusteTms
  return [
    { label: 'En el límite', value: String(s.enLimite), detail: 'usan ≥ 80 % de su tolerancia', tone: s.enLimite ? 'warn' : 'ok' },
    { label: 'Fuera de tolerancia', value: String(s.fuera), detail: s.fuera ? 'revisa las celdas en rojo' : 'todas dentro', tone: s.fuera ? 'danger' : 'ok' },
    {
      label: 'Mayor ajuste TMS',
      value: max ? `${max.ajustePct > 0 ? '+' : ''}${fmt(max.ajustePct, 2)} %` : '—',
      detail: max?.flujo ?? 'sin mediciones',
      tone: 'neutral',
    },
    {
      label: 'Nodos sin cerrar',
      value: `${s.nodosSinCerrar} / ${s.totalNodos}`,
      detail: q.value.nodes.filter(n => !n.cierra).map(n => n.nodo).join(', ') || 'todos cierran',
      tone: s.nodosSinCerrar ? 'danger' : 'ok',
    },
  ] as const
})
const toneClass = { ok: 'bg-ok', warn: 'bg-warn', danger: 'bg-danger', neutral: 'bg-line-strong' }
const nodoNombre = computed(() => (nodo.value === null ? '' : props.sesion.nodos[nodo.value]?.nombre))
const h2 = 'font-display text-[0.9375rem] font-semibold'
</script>

<template>
  <div class="space-y-6">
    <section aria-label="Calidad de la reconciliación" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div v-for="k in kpis" :key="k.label" class="relative overflow-hidden rounded-lg border border-line bg-surface px-4 py-3">
        <span class="absolute inset-y-0 left-0 w-1" :class="toneClass[k.tone]" aria-hidden="true" />
        <p class="text-xs text-ink-muted">{{ k.label }}</p>
        <p class="mt-0.5 font-display text-xl font-semibold">{{ k.value }}</p>
        <p class="truncate text-xs text-ink-faint" :title="k.detail">{{ k.detail }}</p>
      </div>
    </section>

    <section aria-labelledby="t-flujos">
      <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 id="t-flujos" :class="h2">Flujos</h2>
        <div v-if="nodoNombre" class="flex flex-wrap items-center gap-3 text-xs" role="status">
          <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[var(--flow-in)]" aria-hidden="true" /> Entra a {{ nodoNombre }}</span>
          <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[var(--flow-out)]" aria-hidden="true" /> Sale de {{ nodoNombre }}</span>
          <button type="button" class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-ink-muted hover:bg-surface-2 hover:text-ink" @click="nodo = null">
            <X class="size-3.5" aria-hidden="true" /> Quitar
          </button>
        </div>
        <p v-else class="text-xs text-ink-muted">
          {{ editable ? 'Las celdas sombreadas se editan. ' : '' }}Elige un nodo para ver sus flujos.
        </p>
      </div>
      <FlujosGrid :sesion="sesion" :editable="editable" :locked="locked" :nodo="nodo" @edit="emit('edit')" />
      <p class="mt-1.5 text-xs text-ink-muted">
        <span class="font-semibold text-warn">!</span> en el límite (≥ 80 % de la tolerancia) ·
        <span class="font-semibold text-danger">‼</span> fuera de tolerancia<template v-if="nodoNombre"> · → entra a {{ nodoNombre }} · ← sale de {{ nodoNombre }}</template>
        <template v-if="locked && editable"> · Edición en pausa mientras termina la operación.</template>
      </p>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
      <section aria-labelledby="t-nodos">
        <h2 id="t-nodos" :class="h2" class="mb-2">Nodos <span class="font-sans text-xs font-normal text-ink-muted">· residuos del balance</span></h2>
        <NodosGrid v-model:nodo="nodo" :sesion="sesion" />
      </section>
      <section v-if="sesion.inventarios.length" aria-labelledby="t-inv">
        <h2 id="t-inv" :class="h2" class="mb-2">Inventarios</h2>
        <InventariosGrid :sesion="sesion" :editable="editable" :locked="locked" @edit="emit('edit')" />
      </section>
    </div>
  </div>
</template>
