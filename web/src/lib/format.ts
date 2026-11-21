/** Display helpers: es-CL numbers and dates, and the valley color (color says which valley, nothing else). */

const nf = new Map<string, Intl.NumberFormat>()
export function fmt(value: number | null | undefined, decimals = 0, minDecimals = decimals): string {
  if (value === null || value === undefined || !Number.isFinite(value)) return '—'
  const key = `${decimals}.${minDecimals}`
  if (!nf.has(key)) nf.set(key, new Intl.NumberFormat('es-CL', { minimumFractionDigits: minDecimals, maximumFractionDigits: decimals }))
  // No "-0" for values that round to zero.
  return nf.get(key)!.format(Number(value.toFixed(decimals)) === 0 ? 0 : value)
}

export function fecha(iso: string | null | undefined, withTime = false): string {
  if (!iso) return '—'
  // Plain dates (periods) are local calendar days, not UTC instants.
  const d = /^\d{4}-\d{2}-\d{2}$/.test(iso) ? new Date(`${iso}T00:00:00`) : new Date(iso)
  return d.toLocaleString('es-CL', withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' })
}

export function periodo(ini: string | null, fin: string | null): string {
  return ini && fin ? `${fecha(ini)} – ${fecha(fin)}` : '—'
}

const VALLE_COLOR: Record<string, string> = {
  copiapo: 'var(--color-valle-copiapo)',
  huasco: 'var(--color-valle-huasco)',
  elqui: 'var(--color-valle-elqui)',
}
export function valleColor(nombre: string | null | undefined): string {
  const key = (nombre ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()
  return VALLE_COLOR[key] ?? 'var(--ink-faint)'
}
