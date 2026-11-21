import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { expect, it } from 'vitest'

// No ESLint in web/: this stands in for vue/no-v-html (v-html renders unescaped HTML, an XSS risk).
function vueFiles(dir: string): string[] {
  return readdirSync(dir, { withFileTypes: true }).flatMap(e =>
    e.isDirectory() ? vueFiles(join(dir, e.name)) : e.name.endsWith('.vue') ? [join(dir, e.name)] : [],
  )
}

it('never uses v-html', () => {
  const offenders = vueFiles(join(__dirname, '../..')).filter(f => /\sv-html\b/.test(readFileSync(f, 'utf8')))
  expect(offenders).toEqual([])
})
