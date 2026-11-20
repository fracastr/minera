// Valley colors from cmp.cl, matched by name so they hold whatever the DB id or accents are.
const VALLE_COLORS = { copiapo: '#B13F95', huasco: '#00837A', elqui: '#FF6462' }

export const valleColor = (nombre, fallback) => {
  const key = (nombre || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
  const match = Object.keys(VALLE_COLORS).find(k => key.includes(k))

  return match ? VALLE_COLORS[match] : fallback
}
