<script setup lang="ts">
import type { CellClassParams, CellValueChangedEvent, ColDef, ColGroupDef, GridApi, RowClassParams, ValueFormatterParams } from 'ag-grid-community'
import { AgGridVue } from 'ag-grid-vue3'
import { computed, shallowRef, toRaw, watch } from 'vue'
import { nodeStreams } from '@/domain/flowGraph'
import { ajuste } from '@/domain/reconciliationQuality'
import type { Flujo, Sesion, Variable } from '@/domain/types'
import { fmt } from '@/lib/format'
import { columnTypes, gridTheme, parseNumber } from '@/lib/grid'

// One grid for what v1 split into two scroll-synced grids: per variable, measured / reconciled / tolerances / deviations.
const props = defineProps<{ sesion: Sesion; editable: boolean; nodo: number | null }>()
const emit = defineEmits<{ edit: [] }>()

const api = shallowRef<GridApi<Flujo>>()
const streams = computed(() => (props.nodo === null ? null : nodeStreams(props.sesion.matriz, props.nodo)))
watch(streams, () => api.value?.redrawRows())

const num = (decimals: number, min = decimals) => (p: ValueFormatterParams) => fmt(p.value as number, decimals, min)
const editableCol = (field: string, headerName: string, decimals: number, min = decimals): ColDef<Flujo> => ({
  field: field as never, headerName, type: 'num', width: 96,
  editable: props.editable, valueParser: parseNumber, valueFormatter: num(decimals, min),
  cellClass: props.editable ? 'cell-edit' : undefined,
})

function variableGroup(label: string, path: string, pick: (f: Flujo) => Variable, decimals: number): ColGroupDef<Flujo> {
  const estado = (p: CellClassParams<Flujo>) => {
    const a = p.data && ajuste(pick(p.data), p.data.jerarquia)
    return a && (a.estado === 'fuera' || a.estado === 'limite') ? `cell-${a.estado}` : undefined
  }
  return {
    headerName: label,
    children: [
      editableCol(`${path}.medido`, 'Medido', decimals),
      {
        field: `${path}.reconciliado` as never, headerName: 'Reconciliado', type: 'num', width: 112,
        valueFormatter: num(decimals), cellClass: estado,
        tooltipValueGetter: p => {
          const a = p.data && ajuste(pick(p.data), p.data.jerarquia)
          if (!a) return undefined
          const uso = a.usoPct === null ? 'sin tolerancia acotada' : `${fmt(a.usoPct, 0)} % de la tolerancia`
          return `Ajuste ${a.ajustePct > 0 ? '+' : ''}${fmt(a.ajustePct, 2)} % · ${uso}`
        },
      },
      editableCol(`${path}.tol_inf`, 'Tol. − %', 2, 0),
      editableCol(`${path}.tol_sup`, 'Tol. + %', 2, 0),
      { field: `${path}.desv_inf` as never, headerName: 'Desv. − %', type: 'num', width: 92, valueFormatter: num(2) },
      { field: `${path}.desv_sup` as never, headerName: 'Desv. + %', type: 'num', width: 92, valueFormatter: num(2) },
    ],
  }
}

// On phones pinned columns would fill the screen; there the name scrolls with the rest.
const pin = window.matchMedia('(min-width: 640px)').matches ? ('left' as const) : undefined

const columnDefs = computed<(ColDef<Flujo> | ColGroupDef<Flujo>)[]>(() => [
  { headerName: '#', valueGetter: p => (p.data?.idx ?? 0) + 1, width: 52, pinned: pin, type: 'num', cellClass: 'text-ink-faint' },
  {
    field: 'nombre', headerName: 'Flujo', pinned: pin, width: pin ? 220 : 170, cellClass: 'cell-flujo',
    valueFormatter: p => (p.data?.stock ? `${p.value} · stock` : String(p.value)),
    tooltipValueGetter: p => [p.data?.tag, p.data?.descripcion].filter(Boolean).join(' · ') || undefined,
  },
  variableGroup('TMS', 'tms', f => f.tms, 0),
  ...props.sesion.componentes.map((c, k) => variableGroup(`${c} %`, `leyes.${k}`, f => f.leyes[k]!, 2)),
  {
    field: 'jerarquia', headerName: 'Jerarquía', width: 92, type: 'num',
    headerTooltip: '1: el solver respeta las tolerancias. 2: usa límites amplios.',
    editable: props.editable, cellClass: props.editable ? 'cell-edit' : undefined,
    valueParser: p => (['1', '2'].includes(String(p.newValue).trim()) ? Number(p.newValue) : p.oldValue),
  },
])

const rowClassRules = {
  'row-in': (p: RowClassParams<Flujo>) => !!p.data && !!streams.value?.entran.includes(p.data.idx),
  'row-out': (p: RowClassParams<Flujo>) => !!p.data && !!streams.value?.salen.includes(p.data.idx),
}

// The grid edits its own copy of the rows (ag-grid-vue3 does not write through Vue proxies): copy the row back.
function onChange(e: CellValueChangedEvent<Flujo>) {
  if (!e.data || e.oldValue === e.newValue) return
  Object.assign(props.sesion.flujos[e.data.idx]!, structuredClone(toRaw(e.data)))
  emit('edit')
}

// Fits the rows up to ~16, then scrolls inside the grid.
const height = computed(() => `${Math.min(props.sesion.flujos.length, 16) * 30 + 2 * 32 + 4}px`)
</script>

<template>
  <AgGridVue
    :style="{ height }"
    :theme="gridTheme"
    :column-types="columnTypes"
    :row-data="sesion.flujos"
    :column-defs="columnDefs"
    :default-col-def="{ resizable: true, sortable: false, suppressMovable: true }"
    :get-row-id="(p: { data: Flujo }) => String(p.data.idx)"
    :row-class-rules="rowClassRules"
    :tooltip-show-delay="300"
    :single-click-edit="true"
    :stop-editing-when-cells-lose-focus="true"
    @grid-ready="(e: { api: GridApi<Flujo> }) => (api = e.api)"
    @cell-value-changed="onChange"
  />
</template>
