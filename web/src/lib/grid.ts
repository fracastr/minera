import {
  CellStyleModule,
  ClientSideRowModelModule,
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

/**
 * Accepts what people type in Chile ("1.234,5", "1234,5", "17.900" as thousands) and plain "1234.5";
 * anything else keeps the old value.
 */
export function parseNumber(p: Pick<ValueParserParams, 'newValue' | 'oldValue'>): number {
  const raw = String(p.newValue ?? '').trim().replace(/\s/g, '')
  const thousands = /^-?\d{1,3}(\.\d{3})+$/.test(raw)
  const normalized = raw.includes(',') || thousands ? raw.replace(/\./g, '').replace(',', '.') : raw
  const n = Number(normalized)
  return raw !== '' && Number.isFinite(n) ? n : (p.oldValue as number)
}
