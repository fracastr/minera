<script setup lang="ts">
import type { CellValueChangedEvent, ColDef, ValueParserParams } from 'ag-grid-community'
import { AgGridVue } from 'ag-grid-vue3'
import { computed, toRaw } from 'vue'
import { recomputeInventario, type Inventario, type Sesion } from '@/domain/types'
import { notify } from '@/lib/feedback'
import { fmt } from '@/lib/format'
import { columnTypes, gridTheme, localeText, parseNumber, parsePct, rowIdByIdx } from '@/lib/grid'

const props = defineProps<{ sesion: Sesion; editable: boolean; locked: boolean }>()
const emit = defineEmits<{ edit: [] }>()

const canEdit = () => props.editable && !props.locked
const col = (field: string, headerName: string, decimals: number, parser?: (p: ValueParserParams) => number): ColDef<Inventario> => ({
  field: field as never, headerName, type: 'num', width: 100,
  valueFormatter: p => fmt(p.value, decimals),
  ...(parser && props.editable ? { editable: canEdit, valueParser: parser, cellClass: 'cell-edit' } : {}),
})

// Moisture of 100 % would divide by zero in TMH = TMS / (1 − h/100).
function parseHumedad(p: ValueParserParams): number {
  const n = parsePct(p)
  if (n < 100) return n
  notify('danger', 'La humedad debe ser menor que 100 %.')
  return p.oldValue as number
}

const columnDefs = computed<ColDef<Inventario>[]>(() => [
  { field: 'nombre', headerName: 'Inventario', pinned: window.matchMedia('(min-width: 640px)').matches ? 'left' : undefined, width: 130 },
  col('tmh_ini', 'TMH ini', 0),
  col('tmh_fin', 'TMH fin', 0),
  col('tmh_delta', 'TMH Δ', 0),
  col('humedad', 'Humedad %', 2, parseHumedad),
  col('tms_ini', 'TMS ini', 0, parseNumber),
  col('tms_fin', 'TMS fin', 0, parseNumber),
  col('tms_delta', 'TMS Δ', 0),
  ...props.sesion.componentes.map((c, k): ColDef<Inventario> => ({ ...col(`leyes.${k}`, `${c} %`, 2, parsePct), width: 88 })),
])

// Deltas follow the edit right away; the server applies the same rule when it saves.
function onChange(e: CellValueChangedEvent<Inventario>) {
  if (!e.data || e.oldValue === e.newValue) return
  recomputeInventario(e.data)
  e.api.refreshCells({ rowNodes: [e.node], force: true })
  // The grid edits its own copy of the rows: copy the row back into the session.
  const row = props.sesion.inventarios.find(i => i.idx === e.data!.idx)
  if (row) Object.assign(row, structuredClone(toRaw(e.data)))
  emit('edit')
}
</script>

<template>
  <AgGridVue
    aria-label="Inventarios"
    dom-layout="autoHeight"
    :theme="gridTheme"
    :locale-text="localeText"
    :column-types="columnTypes"
    :row-data="sesion.inventarios"
    :column-defs="columnDefs"
    :default-col-def="{ resizable: true, sortable: false, suppressMovable: true }"
    :get-row-id="rowIdByIdx"
    :single-click-edit="true"
    :stop-editing-when-cells-lose-focus="true"
    @cell-value-changed="onChange"
  />
</template>
