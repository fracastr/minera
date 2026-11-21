// Standalone SVG / PNG of a rendered Vue Flow diagram whose nodes and edges are drawn in SVG.
// Computed styles are inlined so the file looks the same outside the app (theme included).
// Reused by the Excel export (S5) to send the diagram image.

const PROPS = [
  'fill', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-dasharray', 'stroke-linecap', 'stroke-linejoin', 'stroke-opacity',
  'opacity', 'font-family', 'font-size', 'font-weight', 'font-style', 'letter-spacing', 'text-anchor', 'dominant-baseline',
  'paint-order', 'visibility', 'display',
] as const

function inline(src: Element, dst: Element) {
  const cs = getComputedStyle(src)
  dst.setAttribute('style', PROPS.map(p => `${p}:${cs.getPropertyValue(p)}`).join(';'))
  dst.removeAttribute('class')
  dst.removeAttribute('tabindex')
  dst.removeAttribute('role')
  const a = src.children
  const b = dst.children
  for (let i = 0; i < a.length; i++) inline(a[i]!, b[i]!)
}

function cloneInlined(el: SVGElement): SVGElement {
  const c = el.cloneNode(true) as SVGElement
  inline(el, c)
  // Hit areas are invisible helpers: drop them from the file.
  c.querySelectorAll('[data-export="skip"]').forEach(n => n.remove())
  return c
}

const NS = 'http://www.w3.org/2000/svg'

let fontCss: Promise<string> | null = null
/**
 * Work Sans (latin subset) as @font-face rules with the woff2 files inlined, read from the Google Fonts
 * stylesheet the page already loads, so the file renders with the app's font anywhere. '' when unavailable
 * (offline, blocked): the export then falls back to the system sans-serif.
 */
function workSansCss(): Promise<string> {
  return (fontCss ??= (async () => {
    const href = document.querySelector<HTMLLinkElement>('link[rel="stylesheet"][href*="fonts.googleapis.com"]')?.href
    if (!href) return ''
    const css = await (await fetch(href)).text()
    const blocks = (css.match(/@font-face\s*{[^}]*}/g) ?? []).filter(b => /Work Sans/.test(b) && /U\+0000-00FF/.test(b))
    const files = new Map<string, Promise<string>>()
    const dataUri = (url: string) => {
      if (!files.has(url)) {
        files.set(url, fetch(url).then(r => r.blob()).then(b => new Promise<string>((ok, ko) => {
          const fr = new FileReader()
          fr.onload = () => ok(String(fr.result))
          fr.onerror = ko
          fr.readAsDataURL(b)
        })))
      }
      return files.get(url)!
    }
    const out = await Promise.all(blocks.map(async b => {
      const url = /url\(([^)]+)\)/.exec(b)?.[1]
      return url ? b.replace(url, await dataUri(url)) : ''
    }))
    return out.join('\n')
  })().catch(() => ''))
}

export async function diagramSvg(container: HTMLElement, pad = 24): Promise<{ svg: string; width: number; height: number }> {
  const root = document.createElementNS(NS, 'svg')
  const content = document.createElementNS(NS, 'g')
  let x0 = Infinity
  let y0 = Infinity
  let x1 = -Infinity
  let y1 = -Infinity
  const grow = (x: number, y: number, w: number, h: number) => {
    x0 = Math.min(x0, x)
    y0 = Math.min(y0, y)
    x1 = Math.max(x1, x + w)
    y1 = Math.max(y1, y + h)
  }

  container.querySelectorAll<SVGGElement>('.vue-flow__edges g.fs-edge').forEach(g => {
    const b = g.getBBox()
    if (b.width || b.height) grow(b.x, b.y, b.width, b.height)
    content.appendChild(cloneInlined(g))
  })
  container.querySelectorAll<HTMLElement>('.vue-flow__node').forEach(node => {
    const svg = node.querySelector<SVGSVGElement>('svg.fs-node')
    const m = /translate\(\s*(-?[\d.]+)px\s*,\s*(-?[\d.]+)px\s*\)/.exec(node.style.transform)
    if (!svg || !m) return
    const [x, y] = [Number(m[1]), Number(m[2])]
    const w = Number(svg.getAttribute('width'))
    const h = Number(svg.getAttribute('height'))
    const c = cloneInlined(svg)
    c.setAttribute('x', String(x))
    c.setAttribute('y', String(y))
    c.querySelector('title')?.remove()
    c.style.opacity = getComputedStyle(node.firstElementChild ?? node).opacity
    content.appendChild(c)
    grow(x, y, w, h)
  })
  if (!Number.isFinite(x0)) x0 = y0 = x1 = y1 = 0

  const width = Math.ceil(x1 - x0 + 2 * pad)
  const height = Math.ceil(y1 - y0 + 2 * pad)
  const cs = getComputedStyle(container)
  root.setAttribute('xmlns', NS)
  root.setAttribute('viewBox', `${x0 - pad} ${y0 - pad} ${width} ${height}`)
  root.setAttribute('width', String(width))
  root.setAttribute('height', String(height))
  root.setAttribute('style', `font-family:${cs.fontFamily};font-variant-numeric:tabular-nums`)
  const bg = document.createElementNS(NS, 'rect')
  bg.setAttribute('x', String(x0 - pad))
  bg.setAttribute('y', String(y0 - pad))
  bg.setAttribute('width', String(width))
  bg.setAttribute('height', String(height))
  bg.setAttribute('fill', cs.backgroundColor)
  const fonts = await workSansCss()
  if (fonts) {
    const style = document.createElementNS(NS, 'style')
    style.textContent = fonts
    root.append(style)
  }
  root.append(bg, content)
  return { svg: `<?xml version="1.0" encoding="UTF-8"?>\n${new XMLSerializer().serializeToString(root)}`, width, height }
}

/** PNG at `scale`× (2× by default) of the SVG, drawn through a canvas. */
export async function diagramPng(container: HTMLElement, scale = 2): Promise<Blob> {
  const { svg, width, height } = await diagramSvg(container)
  const img = new Image()
  img.decoding = 'async'
  img.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`
  await img.decode()
  const canvas = document.createElement('canvas')
  canvas.width = Math.round(width * scale)
  canvas.height = Math.round(height * scale)
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('El navegador no permite dibujar la imagen.')
  ctx.scale(scale, scale)
  ctx.drawImage(img, 0, 0, width, height)
  return new Promise((resolve, reject) => canvas.toBlob(b => (b ? resolve(b) : reject(new Error('No se pudo generar el PNG.'))), 'image/png'))
}

export function descargar(data: Blob | string, nombre: string, type = 'image/svg+xml') {
  const blob = typeof data === 'string' ? new Blob([data], { type }) : data
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = nombre
  a.click()
  setTimeout(() => URL.revokeObjectURL(a.href), 1000)
}

/** File-safe name: `<proceso>_<id>_diagrama`. */
export const nombreArchivo = (proceso: string, id: number | string) =>
  `${proceso.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\w-]+/g, '_').replace(/^_|_$/g, '')}_${id}_diagrama`

export function useDiagramImage() {
  return { diagramSvg, diagramPng, descargar, nombreArchivo }
}
