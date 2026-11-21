/** v1's suggested period end: quincenal ends on the 15th if it starts on or before it, otherwise (and mensual) on the last day. */
export function sugerirFin(inicio: string, tipo: 'quincenal' | 'mensual'): string {
  const [y, m, d] = inicio.split('-').map(Number) as [number, number, number]
  if (!y || !m || !d) return ''
  const lastDay = new Date(y, m, 0).getDate()
  const day = tipo === 'quincenal' && d <= 15 ? 15 : lastDay
  return `${y}-${String(m).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}
