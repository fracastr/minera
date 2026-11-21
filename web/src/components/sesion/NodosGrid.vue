<script setup lang="ts">
import type { CellClassParams, CellKeyDownEvent, ColDef, GridApi, RowClassParams, RowClickedEvent } from 'ag-grid-community'
import { AgGridVue } from 'ag-grid-vue3'
import { computed, shallowRef, watch } from 'vue'
import type { Nodo, Sesion } from '@/domain/types'
import { fmt } from '@/lib/format'
import { columnTypes, gridTheme, localeText, rowIdByIdx } from '@/lib/grid'

const props = defineProps<{ sesion: Sesion }>()
const nodo = defineModel<number | null>('nodo', { required: true })

const api = shallowRef<GridApi<Nodo>>()
watch(nodo, () => api.value?.redrawRows())

// Same rule as v1: any residual above 0.001 means the node does not close. Marked in text too (‼).
const abierto = (v: unknown) => Math.abs(Number(v)) > 0.001
const residuo = { valueFormatter: (p: { value: unknown }) => (abierto(p.value) ? '‼ ' : '') + fmt(p.value as number, 2), cellClass: (p: CellClassParams<Nodo>) => (abierto(p.value) ? 'cell-fuera' : undefined) }

const columnDefs = computed<ColDef<Nodo>[]>(() => [
  {
    field: 'nombre', headerName: 'Nodo', flex: 2, minWidth: 120, cellClass: 'cell-flujo',
    valueFormatter: p => (p.data?.idx === nodo.value ? `● ${p.value}` : String(p.value)),
  },
  { field: 'residuo_tms', headerName: 'Residuo TMS', type: 'num', flex: 1, minWidth: 88, ...residuo },
  ...props.sesion.componentes.map((c, k): ColDef<Nodo> => ({
    headerName: `Finos ${c}`, type: 'num', flex: 1, minWidth: 84, valueGetter: p => p.data?.residuo_finos[k], ...residuo,
  })),
])

const rowClassRules = { 'row-sel': (p: RowClassParams<Nodo>) => p.data?.idx === nodo.value }
const toggle = (idx: number) => (nodo.value = nodo.value === idx ? null : idx)
const onClick = (e: RowClickedEvent<Nodo>) => e.data && toggle(e.data.idx)
// Keyboard: Enter or Space on any cell of the row selects or clears the node.
function onKey(e: CellKeyDownEvent<Nodo>) {
  const key = (e.event as KeyboardEvent | undefined)?.key
  if (e.data && (key === 'Enter' || key === ' ')) {
    e.event?.preventDefault()
    toggle(e.data.idx)
  }
}
</script>

<template>
  <AgGridVue
    aria-label="Nodos: Enter o Espacio selecciona un nodo y resalta sus flujos"
    dom-layout="autoHeight"
    :theme="gridTheme"
    :locale-text="localeText"
    :column-types="columnTypes"
    :row-data="sesion.nodos"
    :column-defs="columnDefs"
    :default-col-def="{ resizable: true, sortable: false, suppressMovable: true }"
    :get-row-id="rowIdByIdx"
    :row-class-rules="rowClassRules"
    class="cursor-pointer"
    @grid-ready="(e: { api: GridApi<Nodo> }) => (api = e.api)"
    @row-clicked="onClick"
    @cell-key-down="onKey"
  />
</template>
