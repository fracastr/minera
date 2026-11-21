<script setup lang="ts">
import type { CellClassParams, ColDef, GridApi, RowClassParams, RowClickedEvent } from 'ag-grid-community'
import { AgGridVue } from 'ag-grid-vue3'
import { computed, shallowRef, watch } from 'vue'
import type { Nodo, Sesion } from '@/domain/types'
import { fmt } from '@/lib/format'
import { columnTypes, gridTheme } from '@/lib/grid'

const props = defineProps<{ sesion: Sesion }>()
const nodo = defineModel<number | null>('nodo', { required: true })

const api = shallowRef<GridApi<Nodo>>()
watch(nodo, () => api.value?.redrawRows())

// Same rule as v1: any residual above 0.001 means the node does not close.
const abierto = (p: CellClassParams<Nodo>) => (Math.abs(Number(p.value)) > 0.001 ? 'cell-fuera' : undefined)

const columnDefs = computed<ColDef<Nodo>[]>(() => [
  { field: 'nombre', headerName: 'Nodo', flex: 2, minWidth: 120, cellClass: 'cell-flujo' },
  { field: 'residuo_tms', headerName: 'Residuo TMS', type: 'num', flex: 1, minWidth: 88, valueFormatter: p => fmt(p.value, 2), cellClass: abierto },
  ...props.sesion.componentes.map((c, k): ColDef<Nodo> => ({
    headerName: `Finos ${c}`, type: 'num', flex: 1, minWidth: 84,
    valueGetter: p => p.data?.residuo_finos[k], valueFormatter: p => fmt(p.value, 2), cellClass: abierto,
  })),
])

const rowClassRules = { 'row-sel': (p: RowClassParams<Nodo>) => p.data?.idx === nodo.value }
const toggle = (e: RowClickedEvent<Nodo>) => e.data && (nodo.value = nodo.value === e.data.idx ? null : e.data.idx)
</script>

<template>
  <AgGridVue
    dom-layout="autoHeight"
    :theme="gridTheme"
    :column-types="columnTypes"
    :row-data="sesion.nodos"
    :column-defs="columnDefs"
    :default-col-def="{ resizable: true, sortable: false, suppressMovable: true }"
    :get-row-id="(p: { data: Nodo }) => String(p.data.idx)"
    :row-class-rules="rowClassRules"
    class="cursor-pointer"
    @grid-ready="(e: { api: GridApi<Nodo> }) => (api = e.api)"
    @row-clicked="toggle"
  />
</template>
