import { notify } from '@/lib/feedback'
import {
  CellStyleModule,
  ClientSideRowModelModule,
  LocaleModule,
  ModuleRegistry,
  RowStyleModule,
  TextEditorModule,
  TooltipModule,
  ValidationModule,
  themeQuartz,
  type ValueParserParams,
} from 'ag-grid-community'

// Only what the balance grids use; ValidationModule names a missing module in dev.
ModuleRegistry.registerModules([
  ClientSideRowModelModule,
  TextEditorModule,
  CellStyleModule,
  RowStyleModule,
  TooltipModule,
  LocaleModule, // localeText (Spanish grid texts) needs it
  ...(import.meta.env.DEV ? [ValidationModule] : []),
])

// Colors are our tokens, so the grids follow light/dark with the rest of the app.
export const gridTheme = themeQuartz.withParams({
  fontFamily: 'inherit',
  fontSize: 12.5,
  headerFontSize: 12,
  headerFontWeight: 500,
  spacing: 4,
  rowHeight: 30,
  headerHeight: 32,
  cellHorizontalPadding: 10,
  borderRadius: 4,
  wrapperBorderRadius: 8,
  browserColorScheme: 'inherit',
  accentColor: 'var(--primary)',
  backgroundColor: 'var(--surface)',
  foregroundColor: 'var(--ink)',
  textColor: 'var(--ink)',
  subtleTextColor: 'var(--ink-muted)',
  headerBackgroundColor: 'var(--surface-2)',
  headerTextColor: 'var(--ink-muted)',
  borderColor: 'var(--line)',
  rowBorder: { color: 'var(--line)' },
  columnBorder: { color: 'color-mix(in srgb, var(--line) 60%, transparent)' },
  rowHoverColor: 'color-mix(in srgb, var(--primary) 6%, transparent)',
  selectedRowBackgroundColor: 'var(--primary-soft)',
  inputFocusBorder: { color: 'var(--primary)' },
  focusShadow: { radius: 2, spread: 0, color: 'var(--focus)' },
  pinnedColumnBorder: { color: 'var(--line-strong)' },
  tooltipBackgroundColor: 'var(--rail)',
  tooltipTextColor: '#ffffff',
})

/** Numbers align right; cellStyle (not cellClass) so columns can still set their own classes. */
export const columnTypes = { num: { cellStyle: { textAlign: 'right' }, headerClass: 'ag-right-aligned-header' } }

/** Every balance grid keys rows by the session index, so edits and refreshes keep their place. */
export const rowIdByIdx = (p: { data: { idx: number } }) => String(p.data.idx)

/** The few grid texts our grids can show, in Spanish. */
export const localeText = {
  noRowsToShow: 'Sin filas',
  loadingOoo: 'Cargando…',
  ariaRowSelect: 'Seleccionar fila',
  ariaSearch: 'Buscar',
  ariaColumn: 'Columna',
  ariaRow: 'Fila',
}

/** Same bound the server applies to edited values. */
export const MAX_ABS = 1e12

/**
 * Reads what people type in Chile ("1.234,5", "1234,5", "17.900" as thousands) and plain "1234.5".
 * With `max` (0–100 columns: leyes, tolerancias, humedad), a dot that would make the value exceed it is a
 * decimal point ("0.125" → 0.125, "1.500" → 1.5). Returns null for anything else (letters, hex, ±∞).
 */
export function readNumber(input: unknown, max?: number): number | null {
  const raw = String(input ?? '').trim().replace(/\s/g, '')
  if (!/^-?[\d.,]+$/.test(raw) || !/\d/.test(raw)) return null
  const asThousands = /^-?\d{1,3}(\.\d{3})+$/.test(raw) ? Number(raw.replace(/\./g, '')) : null
  let n: number
  if (raw.includes(',')) n = Number(raw.replace(/\./g, '').replace(',', '.'))
  else if (asThousands !== null && (max === undefined || asThousands <= max)) n = asThousands
  else n = Number(raw)
  return Number.isFinite(n) && Math.abs(n) <= MAX_ABS ? n : null
}

/** AG Grid valueParser: keeps the old value and says why when the input is not a number. */
export function numberParser(max?: number) {
  return (p: Pick<ValueParserParams, 'newValue' | 'oldValue'>): number => {
    const n = readNumber(p.newValue, max)
    if (n !== null && (max === undefined || (n >= 0 && n <= max))) return n
    notify('danger', `«${String(p.newValue ?? '').trim()}» no es un valor válido${max === undefined ? '' : ` (0 a ${max})`}. Ejemplos: 1.234,5 · 1234,5 · 58,66`)
    return p.oldValue as number
  }
}

export const parseNumber = numberParser()
export const parsePct = numberParser(100)
