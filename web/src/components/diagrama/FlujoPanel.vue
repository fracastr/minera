<script setup lang="ts">
import { PencilLine, X } from '@lucide/vue'
import { computed, onMounted, ref, watch } from 'vue'
import { valores } from '@/domain/flowsheet'
import type { Flujo } from '@/domain/types'
import { fmt } from '@/lib/format'

const props = defineProps<{ flujo: Flujo; componentes: string[]; corrida: boolean; editable: boolean }>()
const emit = defineEmits<{ close: []; editar: [] }>()

const filas = computed(() => [
  { label: 'TMS', dec: 0, v: valores(props.flujo, { componente: null, modo: 'ley' }, props.corrida) },
  ...props.componentes.map((c, k) => ({ label: `${c} %`, dec: 2, v: valores(props.flujo, { componente: k, modo: 'ley' }, props.corrida) })),
  ...props.componentes.map((c, k) => ({ label: `Fino ${c} t`, dec: 0, v: valores(props.flujo, { componente: k, modo: 'fino' }, props.corrida) })),
])
const tonoUso = { fuera: 'text-danger font-semibold', limite: 'text-warn font-semibold', ok: '', 'sin-tolerancia': 'text-ink-faint' }
// The panel takes focus when it opens or shows another stream; the parent returns it on close.
const titulo = ref<HTMLElement>()
onMounted(() => titulo.value?.focus())
watch(() => props.flujo.idx, () => titulo.value?.focus())
const signo = (n: number | null) => (n === null ? '—' : `${n > 0 ? '+' : ''}${fmt(n, 2).replace('-', '−')} %`)
</script>

<template>
  <aside
    class="absolute inset-x-0 bottom-0 z-10 max-h-[62%] overflow-y-auto rounded-t-xl border-t border-line bg-surface p-4 shadow-lg sm:inset-x-auto sm:top-0 sm:right-0 sm:max-h-none sm:w-[22rem] sm:rounded-none sm:border-t-0 sm:border-l sm:shadow-none"
    aria-labelledby="fp-title"
    @keydown.esc.stop="emit('close')"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs text-ink-muted">Flujo {{ flujo.idx + 1 }}<template v-if="flujo.stock"> · inventario</template><template v-if="flujo.tag"> · {{ flujo.tag }}</template></p>
        <h3 id="fp-title" ref="titulo" tabindex="-1" class="outline-none font-display text-[0.9375rem] font-semibold">{{ flujo.nombre }}</h3>
        <p v-if="flujo.descripcion" class="mt-0.5 text-xs text-ink-muted">{{ flujo.descripcion }}</p>
      </div>
      <button type="button" class="-mr-1 grid size-8 shrink-0 place-items-center rounded text-ink-muted hover:bg-surface-2 hover:text-ink" aria-label="Cerrar detalle" @click="emit('close')">
        <X class="size-4" aria-hidden="true" />
      </button>
    </div>

    <table class="mt-3 w-full text-right text-xs">
      <thead class="text-ink-muted">
        <tr class="border-b border-line">
          <th scope="col" class="py-1.5 text-left font-medium">Variable</th>
          <th scope="col" class="font-medium">Medido</th>
          <th v-if="corrida" scope="col" class="font-medium">Reconc.</th>
          <th v-if="corrida" scope="col" class="font-medium">Error</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="f in filas" :key="f.label">
          <tr class="border-b border-line/60">
            <th scope="row" class="py-1.5 text-left font-medium">{{ f.label }}</th>
            <td>{{ fmt(f.v.m, f.dec) }}</td>
            <td v-if="corrida">{{ fmt(f.v.r, f.dec) }}</td>
            <td v-if="corrida" :class="tonoUso[f.v.estado ?? 'sin-tolerancia']">{{ signo(f.v.e) }}</td>
          </tr>
          <tr v-if="f.v.tolInf !== null" class="text-ink-muted">
            <td :colspan="corrida ? 4 : 2" class="pb-1.5 pt-0.5 text-left">
              Tolerancia −{{ fmt(f.v.tolInf, 2, 0) }} / +{{ fmt(f.v.tolSup, 2, 0) }} %<template v-if="corrida">
                · <span :class="tonoUso[f.v.estado ?? 'sin-tolerancia']">{{ f.v.usoPct === null ? 'sin tolerancia acotada' : `usa ${fmt(f.v.usoPct, 0)} %` }}</span></template>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
    <p v-if="flujo.jerarquia !== 1" class="mt-2 text-xs text-ink-muted">Jerarquía {{ flujo.jerarquia }}: el solver usa límites amplios, no las tolerancias.</p>
    <p v-if="!corrida" class="mt-2 text-xs text-ink-muted">Corre el balance para ver el valor reconciliado y el uso de tolerancia.</p>

    <button type="button" class="btn btn-quiet mt-4 h-9 w-full" @click="emit('editar')">
      <PencilLine class="size-4" aria-hidden="true" /> {{ editable ? 'Editar en Datos' : 'Ver en Datos' }}
    </button>
  </aside>
</template>
