<script setup lang="ts">
import type { CellValueChangedEvent, ColDef } from 'ag-grid-community'
import { AgGridVue } from 'ag-grid-vue3'
import { computed, toRaw } from 'vue'
import { recomputeInventario, type Inventario, type Sesion } from '@/domain/types'
import { fmt } from '@/lib/format'
import { columnTypes, gridTheme, parseNumber } from '@/lib/grid'

const props = defineProps<{ sesion: Sesion; editable: boolean }>()
const emit = defineEmits<{ edit: [] }>()

const col = (field: string, headerName: string, decimals: number, editable = false): ColDef<Inventario> => ({
  field: field as never, headerName, type: 'num', width: 100,
  valueFormatter: p => fmt(p.value, decimals),
  ...(editable && props.editable ? { editable: true, valueParser: parseNumber, cellClass: 'cell-edit' } : {}),
})

const columnDefs = computed<ColDef<Inventario>[]>(() => [
  { field: 'nombre', headerName: 'Inventario', pinned: window.matchMedia('(min-width: 640px)').matches ? 'left' : undefined, width: 130 },
  col('tmh_ini', 'TMH ini', 0),
  col('tmh_fin', 'TMH fin', 0),
  col('tmh_delta', 'TMH Δ', 0),
  { ...col('humedad', 'Humedad %', 2, true), valueParser: p => {
    const n = parseNumber(p)
    return n >= 0 && n < 100 ? n : p.oldValue
  } },
  col('tms_ini', 'TMS ini', 0, true),
  col('tms_fin', 'TMS fin', 0, true),
  col('tms_delta', 'TMS Δ', 0),
  ...props.sesion.componentes.map((c, k): ColDef<Inventario> => ({ ...col(`leyes.${k}`, `${c} %`, 2, true), width: 88 })),
])

// Deltas follow the edit right away; the server applies the same rule when it saves.
function onChange(e: CellValueChangedEvent<Inventario>) {
  if (!e.data || e.oldValue === e.newValue) return
  recomputeInventario(e.data)
  e.api.refreshCells({ rowNodes: [e.node], force: true })
  // The grid edits its own copy of the rows: copy the row back into the session.
  Object.assign(props.sesion.inventarios[e.data.idx]!, structuredClone(toRaw(e.data)))
  emit('edit')
}
</script>

<template>
  <AgGridVue
    dom-layout="autoHeight"
    :theme="gridTheme"
    :column-types="columnTypes"
    :row-data="sesion.inventarios"
    :column-defs="columnDefs"
    :default-col-def="{ resizable: true, sortable: false, suppressMovable: true }"
    :get-row-id="(p: { data: Inventario }) => String(p.data.idx)"
    :single-click-edit="true"
    :stop-editing-when-cells-lose-focus="true"
    @cell-value-changed="onChange"
  />
</template>
