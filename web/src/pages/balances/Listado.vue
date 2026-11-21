<script setup lang="ts">
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, FilePlus, Search, X } from '@lucide/vue'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Notice from '@/components/Notice.vue'
import type { BalanceRow, Valle } from '@/domain/types'
import { can } from '@/lib/abilities'
import { api, errorMessage } from '@/lib/api'
import { fecha, periodo, valleColor } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const canCreate = computed(() => can(auth.user?.ability, 'create', 'Balances'))

// Filters live in the URL so coming back from a balance keeps them.
const KEYS = ['valle_id', 'proceso_id', 'tipo', 'desde', 'hasta', 'user_id', 'q', 'orden', 'dir', 'page'] as const
type Key = (typeof KEYS)[number]
const f = reactive(Object.fromEntries(KEYS.map(k => [k, String(route.query[k] ?? '')])) as Record<Key, string>)
if (!f.orden) f.orden = 'created_at'
if (!f.dir) f.dir = 'desc'

const valles = ref<Valle[]>([])
const usuarios = ref<{ id: number; nombre: string }[]>([])
const rows = ref<BalanceRow[]>([])
const meta = ref({ total: 0, page: 1, per_page: 25, last_page: 1 })
const loading = ref(true)
const error = ref('')

const procesos = computed(() => (f.valle_id ? valles.value.filter(v => String(v.id) === f.valle_id) : valles.value).flatMap(v => v.procesos))
const hasFilters = computed(() => ['valle_id', 'proceso_id', 'tipo', 'desde', 'hasta', 'user_id', 'q'].some(k => f[k as Key]))

let seq = 0
async function load() {
  const query = Object.fromEntries(Object.entries(f).filter(([, v]) => v))
  router.replace({ query })
  const mine = ++seq
  loading.value = true
  try {
    const res = await api<{ data: BalanceRow[]; meta: typeof meta.value; usuarios: typeof usuarios.value }>('/v2/balances', { query })
    if (mine !== seq) return
    rows.value = res.data
    meta.value = res.meta
    usuarios.value = res.usuarios
    error.value = ''
  } catch (e) {
    if (mine === seq) error.value = errorMessage(e, 'No se pudo cargar el listado.')
  } finally {
    if (mine === seq) loading.value = false
  }
}

let timer: number | undefined
onBeforeUnmount(() => window.clearTimeout(timer))
watch(() => f.q, () => {
  window.clearTimeout(timer)
  timer = window.setTimeout(() => ((f.page = ''), load()), 300)
})
watch(() => [f.valle_id, f.proceso_id, f.tipo, f.desde, f.hasta, f.user_id], () => {
  if (f.proceso_id && !procesos.value.some(p => String(p.id) === f.proceso_id)) f.proceso_id = ''
  f.page = ''
  load()
})

onMounted(async () => {
  load()
  try {
    valles.value = (await api<{ valles: Valle[] }>('/v2/catalogo')).valles
  } catch {} // filters by valle/proceso just stay empty
})

const columns = [
  { key: 'nombre', label: 'Balance' },
  { key: 'proceso', label: 'Proceso' },
  { key: 'tipo', label: 'Tipo' },
  { key: 'periodo_inicio', label: 'Período' },
  { key: 'usuario', label: 'Usuario' },
  { key: 'created_at', label: 'Creado' },
] as const

function sortBy(key: string) {
  f.dir = f.orden === key && f.dir === 'asc' ? 'desc' : 'asc'
  f.orden = key
  load()
}
function goPage(p: number) {
  f.page = p > 1 ? String(p) : ''
  load()
}
function clear() {
  Object.assign(f, { valle_id: '', proceso_id: '', tipo: '', desde: '', hasta: '', user_id: '', q: '' })
}
function open(r: BalanceRow) {
  if (r.datos_entrada_id) router.push({ name: 'balance-sesion', params: { id: r.datos_entrada_id } })
}
const ariaSort = (key: string) => (f.orden === key ? (f.dir === 'asc' ? 'ascending' : 'descending') : undefined)
const tipoLabel = { quincenal: 'Quincenal', mensual: 'Mensual' }
const label = 'mb-1 block text-xs font-medium text-ink-muted'
</script>

<template>
  <div class="mx-auto max-w-7xl">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="font-display text-xl font-semibold">Balances</h1>
        <p class="mt-1 text-ink-muted">
          <template v-if="!loading || rows.length">{{ meta.total }} {{ meta.total === 1 ? 'balance guardado' : 'balances guardados' }}</template>
          <template v-else>Cargando…</template>
        </p>
      </div>
      <RouterLink v-if="canCreate" :to="{ name: 'balances-nuevo' }" class="btn btn-primary">
        <FilePlus class="size-4" aria-hidden="true" /> Nuevo balance
      </RouterLink>
    </div>

    <section aria-label="Filtros" class="mt-6 grid grid-cols-2 gap-3 rounded-lg border border-line bg-surface p-3 sm:grid-cols-3 lg:grid-cols-7">
      <div class="col-span-2 sm:col-span-3 lg:col-span-7">
        <label for="f-q" :class="label">Buscar</label>
        <div class="relative">
          <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-ink-faint" aria-hidden="true" />
          <input id="f-q" v-model="f.q" type="search" placeholder="Nombre del balance" class="field pl-8">
        </div>
      </div>
      <div>
        <label for="f-valle" :class="label">Valle</label>
        <select id="f-valle" v-model="f.valle_id" class="field">
          <option value="">Todos</option>
          <option v-for="v in valles" :key="v.id" :value="String(v.id)">{{ v.nombre }}</option>
        </select>
      </div>
      <div>
        <label for="f-proceso" :class="label">Proceso</label>
        <select id="f-proceso" v-model="f.proceso_id" class="field">
          <option value="">Todos</option>
          <option v-for="p in procesos" :key="p.id" :value="String(p.id)">{{ p.nombre }}</option>
        </select>
      </div>
      <div>
        <label for="f-tipo" :class="label">Tipo</label>
        <select id="f-tipo" v-model="f.tipo" class="field">
          <option value="">Todos</option>
          <option value="quincenal">Quincenal</option>
          <option value="mensual">Mensual</option>
        </select>
      </div>
      <div>
        <label for="f-desde" :class="label">Desde</label>
        <input id="f-desde" v-model="f.desde" type="date" class="field">
      </div>
      <div>
        <label for="f-hasta" :class="label">Hasta</label>
        <input id="f-hasta" v-model="f.hasta" type="date" class="field">
      </div>
      <div>
        <label for="f-user" :class="label">Usuario</label>
        <select id="f-user" v-model="f.user_id" class="field">
          <option value="">Todos</option>
          <option v-for="u in usuarios" :key="u.id" :value="String(u.id)">{{ u.nombre }}</option>
        </select>
      </div>
      <div class="flex items-end">
        <button type="button" class="btn btn-quiet w-full px-3" :disabled="!hasFilters" @click="clear">
          <X class="size-4" aria-hidden="true" /> Limpiar
        </button>
      </div>
    </section>

    <Notice v-if="error" tone="danger" class="mt-4">{{ error }}</Notice>

    <div class="mt-4 overflow-x-auto rounded-lg border border-line bg-surface" :class="{ 'opacity-60 transition-opacity': loading && rows.length }">
      <table class="w-full min-w-[56rem] text-left text-[0.8125rem]">
        <thead class="border-b border-line bg-surface-2/60 text-xs text-ink-muted">
          <tr>
            <th v-for="c in columns" :key="c.key" scope="col" :aria-sort="ariaSort(c.key)" class="px-4 py-2.5 font-medium">
              <button type="button" class="-mx-1 inline-flex items-center gap-1 rounded px-1 hover:text-ink" @click="sortBy(c.key)">
                {{ c.label }}
                <component :is="f.dir === 'asc' ? ArrowUp : ArrowDown" v-if="f.orden === c.key" class="size-3.5 text-primary" aria-hidden="true" />
              </button>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading && !rows.length">
            <td :colspan="columns.length" class="px-4 py-10 text-center text-ink-muted">Cargando balances…</td>
          </tr>
          <tr v-else-if="!rows.length">
            <td :colspan="columns.length" class="px-4 py-12 text-center">
              <p class="font-medium">{{ hasFilters ? 'Ningún balance coincide con los filtros.' : 'Todavía no hay balances guardados.' }}</p>
              <button v-if="hasFilters" type="button" class="mt-2 text-primary hover:underline" @click="clear">Limpiar filtros</button>
            </td>
          </tr>
          <tr
            v-for="r in rows" v-else :key="r.id"
            class="cursor-pointer border-b border-line last:border-0 hover:bg-surface-2/60"
            @click="open(r)"
          >
            <td class="px-4 py-2.5">
              <RouterLink
                v-if="r.datos_entrada_id"
                :to="{ name: 'balance-sesion', params: { id: r.datos_entrada_id } }"
                class="font-medium text-ink hover:text-primary hover:underline"
                @click.stop
              >{{ r.nombre }}</RouterLink>
              <span v-else class="font-medium text-ink-muted" title="Sin datos asociados">{{ r.nombre }}</span>
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
              <span class="inline-flex items-center gap-2">
                <span class="size-2 shrink-0 rounded-full" :style="{ background: valleColor(r.valle?.nombre) }" aria-hidden="true" />
                <span>{{ r.proceso?.nombre ?? '—' }}</span>
                <span class="text-ink-faint">{{ r.valle?.nombre }}</span>
              </span>
            </td>
            <td class="px-4 py-2.5 text-ink-muted">{{ r.tipo ? tipoLabel[r.tipo] ?? r.tipo : '—' }}</td>
            <td class="px-4 py-2.5 whitespace-nowrap">{{ periodo(r.periodo_inicio, r.periodo_fin) }}</td>
            <td class="px-4 py-2.5 whitespace-nowrap">{{ r.usuario?.nombre ?? 'Sin registrar' }}</td>
            <td class="px-4 py-2.5 whitespace-nowrap text-ink-muted">{{ fecha(r.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="meta.last_page > 1" aria-label="Paginación" class="mt-3 flex items-center justify-between gap-3 text-[0.8125rem] text-ink-muted">
      <span>Página {{ meta.page }} de {{ meta.last_page }}</span>
      <div class="flex gap-2">
        <button type="button" class="btn btn-quiet h-9 px-3" :disabled="meta.page <= 1" @click="goPage(meta.page - 1)">
          <ChevronLeft class="size-4" aria-hidden="true" /> Anterior
        </button>
        <button type="button" class="btn btn-quiet h-9 px-3" :disabled="meta.page >= meta.last_page" @click="goPage(meta.page + 1)">
          Siguiente <ChevronRight class="size-4" aria-hidden="true" />
        </button>
      </div>
    </nav>
  </div>
</template>
