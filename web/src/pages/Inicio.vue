<script setup lang="ts">
import { ArrowDownRight, ArrowRight, ArrowUpRight, CircleCheck, FilePlus, History, RotateCcw } from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import Notice from '@/components/Notice.vue'
import ProcesoChip from '@/components/ProcesoChip.vue'
import { can } from '@/lib/abilities'
import { api, errorMessage } from '@/lib/api'
import { fecha, fmt, periodo } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

interface Reciente {
  id: number
  nombre: string | null
  proceso: string | null
  valle: string | null
  estado: 'guardado' | 'corrido' | 'sin-correr'
  solver_ok: boolean | null
  updated_at: string | null
}
interface Alerta {
  balance_id: number
  sesion_id: number
  nombre: string
  proceso: string | null
  valle: string | null
  periodo_inicio: string | null
  periodo_fin: string | null
  fuera: number
  limite: number
  nodos_sin_cerrar: number
  solver_ok: boolean | null
}
interface Sesgo {
  proceso_id: number
  proceso: string | null
  valle: string | null
  flujo: string
  tag: string | null
  variable: string
  signo: 1 | -1
  ultimo_ajuste_pct: number | null
  balances: number
}

const auth = useAuthStore()
const firstName = computed(() => auth.user?.fullName.split(/\s+/)[0] ?? '')
const canCreate = computed(() => can(auth.user?.ability, 'create', 'Balances'))
const hoy = new Date().toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' })

const data = ref<{ recientes: Reciente[]; alertas: Alerta[]; sesgos: Sesgo[] } | null>(null)
const error = ref('')

async function load() {
  error.value = ''
  try {
    data.value = await api('/v2/inicio')
  } catch (e) {
    error.value = errorMessage(e, 'No se pudo cargar el inicio.')
  }
}
onMounted(load)

const peso = (a: Alerta) => a.fuera * 100 + (a.solver_ok === false ? 50 : 0) + a.nodos_sin_cerrar * 10 + a.limite
const conAlerta = computed(() => (data.value?.alertas ?? []).filter(a => peso(a) > 0).sort((a, b) => peso(b) - peso(a)))
const sinAlerta = computed(() => (data.value?.alertas ?? []).filter(a => peso(a) === 0))
const ultimo = computed(() => data.value?.recientes[0])
const SESGOS = 6

const estado = {
  guardado: { label: 'Guardado', cls: 'bg-ok-soft text-ok' },
  corrido: { label: 'Corrido', cls: 'bg-primary-soft text-primary' },
  'sin-correr': { label: 'Sin correr', cls: 'bg-surface-2 text-ink-muted' },
}
const plural = (n: number, uno: string, varios: string) => `${n} ${n === 1 ? uno : varios}`
const card = 'rounded-lg border border-line bg-surface'
</script>

<template>
  <div class="mx-auto max-w-6xl">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="font-display text-xl font-semibold">Hola, {{ firstName }}</h1>
        <p class="mt-1 text-ink-muted first-letter:uppercase">{{ hoy }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink v-if="ultimo" :to="{ name: 'balance-sesion', params: { id: ultimo.id } }" class="btn btn-quiet">
          <History class="size-4" aria-hidden="true" /> Continuar último
        </RouterLink>
        <RouterLink v-if="canCreate" :to="{ name: 'balances-nuevo' }" class="btn btn-primary">
          <FilePlus class="size-4" aria-hidden="true" /> Nuevo balance
        </RouterLink>
      </div>
    </div>

    <Notice v-if="error" tone="danger" class="mt-6">
      {{ error }}
      <button type="button" class="ml-1 inline-flex items-center gap-1 font-medium text-primary hover:underline" @click="load">
        <RotateCcw class="size-3.5" aria-hidden="true" /> Reintentar
      </button>
    </Notice>

    <div class="mt-6 grid gap-4 lg:grid-cols-5" :aria-busy="!data && !error">
      <!-- Alerts: the latest saved balance of each process -->
      <section :class="card" class="lg:col-span-3" aria-labelledby="h-alertas">
        <header class="flex items-baseline justify-between gap-3 border-b border-line px-4 py-3">
          <h2 id="h-alertas" class="text-sm font-semibold">Balances con alertas</h2>
          <span class="hidden text-xs text-ink-muted sm:inline">Último balance guardado de cada proceso</span>
        </header>
        <ul v-if="!data" class="divide-y divide-line" aria-hidden="true">
          <li v-for="i in 3" :key="i" class="space-y-2 px-4 py-3.5">
            <div class="h-4 w-40 animate-pulse rounded bg-surface-2" />
            <div class="h-3 w-64 max-w-full animate-pulse rounded bg-surface-2" />
          </li>
        </ul>
        <template v-else>
          <ul v-if="conAlerta.length" class="divide-y divide-line">
            <li v-for="a in conAlerta" :key="a.balance_id">
              <RouterLink :to="{ name: 'balance-sesion', params: { id: a.sesion_id } }" class="group block px-4 py-3 hover:bg-surface-2/60">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                  <ProcesoChip :proceso="a.proceso" :valle="a.valle" />
                  <span class="min-w-0 truncate font-medium group-hover:text-primary">{{ a.nombre }}</span>
                  <span v-if="a.periodo_fin" class="text-xs text-ink-muted">{{ periodo(a.periodo_inicio, a.periodo_fin) }}</span>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5 text-xs font-medium">
                  <span v-if="a.fuera" class="rounded bg-danger-soft px-1.5 py-0.5 text-danger">{{ plural(a.fuera, 'medición fuera de tolerancia', 'mediciones fuera de tolerancia') }}</span>
                  <span v-if="a.solver_ok === false" class="rounded bg-danger-soft px-1.5 py-0.5 text-danger">El solver no convergió</span>
                  <span v-if="a.nodos_sin_cerrar" class="rounded bg-warn-soft px-1.5 py-0.5 text-warn">{{ plural(a.nodos_sin_cerrar, 'nodo sin cerrar', 'nodos sin cerrar') }}</span>
                  <span v-if="a.limite" class="rounded bg-warn-soft px-1.5 py-0.5 text-warn">{{ plural(a.limite, 'en el límite', 'en el límite') }}</span>
                </div>
              </RouterLink>
            </li>
          </ul>
          <div v-else-if="data.alertas.length" class="flex items-center gap-3 px-4 py-8">
            <CircleCheck class="size-5 shrink-0 text-ok" aria-hidden="true" />
            <p>Los últimos balances de cada proceso cierran dentro de tolerancia.</p>
          </div>
          <p v-else class="px-4 py-8 text-ink-muted">Todavía no hay balances guardados.</p>
          <p v-if="conAlerta.length && sinAlerta.length" class="border-t border-line px-4 py-2.5 text-xs text-ink-muted">
            Sin alertas: {{ sinAlerta.map(a => a.proceso ?? 'Sin proceso').join(', ') }}.
          </p>
        </template>
      </section>

      <!-- Recurring bias -->
      <section :class="card" class="lg:col-span-2" aria-labelledby="h-sesgos">
        <header class="border-b border-line px-4 py-3">
          <h2 id="h-sesgos" class="text-sm font-semibold">Medidores con sesgo recurrente</h2>
          <p class="mt-0.5 text-xs text-ink-muted">El balance los corrige hacia el mismo lado en al menos 3 de los últimos 4 períodos.</p>
        </header>
        <ul v-if="!data" class="divide-y divide-line" aria-hidden="true">
          <li v-for="i in 3" :key="i" class="space-y-2 px-4 py-3.5">
            <div class="h-4 w-32 animate-pulse rounded bg-surface-2" />
            <div class="h-3 w-48 animate-pulse rounded bg-surface-2" />
          </li>
        </ul>
        <template v-else>
          <ul v-if="data.sesgos.length" class="divide-y divide-line">
            <li v-for="s in data.sesgos.slice(0, SESGOS)" :key="`${s.proceso_id}|${s.flujo}|${s.tag}|${s.variable}`" class="flex items-start gap-3 px-4 py-3">
              <component
                :is="s.signo > 0 ? ArrowUpRight : ArrowDownRight"
                class="mt-0.5 size-4 shrink-0 text-warn" aria-hidden="true"
              />
              <div class="min-w-0 flex-1">
                <p class="truncate font-medium">
                  {{ s.flujo }} <span class="font-normal text-ink-muted">· {{ s.variable }}</span>
                </p>
                <p class="text-xs text-ink-muted">
                  <span v-if="s.tag">{{ s.tag }} · </span>
                  {{ s.signo > 0 ? 'Mide bajo: el balance lo sube' : 'Mide alto: el balance lo baja' }}
                  <template v-if="s.ultimo_ajuste_pct !== null"> ({{ s.ultimo_ajuste_pct > 0 ? '+' : '' }}{{ fmt(s.ultimo_ajuste_pct, 1, 1) }} % en el último)</template>
                </p>
                <ProcesoChip :proceso="s.proceso" :valle="s.valle" class="mt-1.5" />
              </div>
            </li>
          </ul>
          <div v-else class="flex items-center gap-3 px-4 py-8">
            <CircleCheck class="size-5 shrink-0 text-ok" aria-hidden="true" />
            <p>Ningún medidor muestra un sesgo que se repita.</p>
          </div>
          <p v-if="data.sesgos.length > SESGOS" class="border-t border-line px-4 py-2.5 text-xs text-ink-muted">
            Y {{ plural(data.sesgos.length - SESGOS, 'medidor más', 'medidores más') }}. Revísalos en el Diagnóstico de cada balance.
          </p>
        </template>
      </section>

      <!-- The user's recent sessions -->
      <section :class="card" class="lg:col-span-5" aria-labelledby="h-recientes">
        <header class="flex items-baseline justify-between gap-3 border-b border-line px-4 py-3">
          <h2 id="h-recientes" class="text-sm font-semibold">Tus balances recientes</h2>
          <RouterLink :to="{ name: 'balances' }" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
            Ver listado <ArrowRight class="size-3.5" aria-hidden="true" />
          </RouterLink>
        </header>
        <ul v-if="!data" class="grid gap-px bg-line sm:grid-cols-2 lg:grid-cols-3" aria-hidden="true">
          <li v-for="i in 3" :key="i" class="space-y-2 bg-surface px-4 py-3.5">
            <div class="h-4 w-36 animate-pulse rounded bg-surface-2" />
            <div class="h-3 w-24 animate-pulse rounded bg-surface-2" />
          </li>
        </ul>
        <ul v-else-if="data.recientes.length" class="grid gap-px overflow-hidden rounded-b-lg bg-line sm:grid-cols-2 lg:grid-cols-3">
          <li v-for="r in data.recientes" :key="r.id" class="bg-surface">
            <RouterLink :to="{ name: 'balance-sesion', params: { id: r.id } }" class="group block h-full px-4 py-3 hover:bg-surface-2/60">
              <div class="flex items-start justify-between gap-2">
                <span class="min-w-0 truncate font-medium group-hover:text-primary" :class="{ 'text-ink-muted italic': !r.nombre }">{{ r.nombre ?? 'Sin guardar' }}</span>
                <span class="shrink-0 rounded px-1.5 py-0.5 text-[0.6875rem] font-medium" :class="estado[r.estado].cls">{{ estado[r.estado].label }}</span>
              </div>
              <div class="mt-2 flex flex-wrap items-center gap-2">
                <ProcesoChip :proceso="r.proceso" :valle="r.valle" />
                <span v-if="r.solver_ok === false" class="rounded bg-danger-soft px-1.5 py-0.5 text-[0.6875rem] font-medium text-danger">Solver sin converger</span>
              </div>
              <p class="mt-1.5 text-xs text-ink-muted">{{ fecha(r.updated_at, true) }}</p>
            </RouterLink>
          </li>
        </ul>
        <div v-else class="px-4 py-10 text-center">
          <p class="font-medium">Aún no tienes balances propios.</p>
          <p class="mt-1 text-ink-muted">{{ canCreate ? 'Sube un Excel para empezar uno, o abre uno del listado.' : 'Puedes revisar los balances del equipo en el listado.' }}</p>
          <div class="mt-4 flex justify-center gap-2">
            <RouterLink v-if="canCreate" :to="{ name: 'balances-nuevo' }" class="btn btn-primary">Nuevo balance</RouterLink>
            <RouterLink :to="{ name: 'balances' }" class="btn btn-quiet">Ver listado</RouterLink>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
