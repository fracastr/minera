<script setup lang="ts">
import { CircleAlert, CircleCheck, EyeOff, LoaderCircle, MapPin, RotateCcw, Wand2 } from '@lucide/vue'
import { computed, onMounted, ref, watch } from 'vue'
import EChart, { type Tokens } from '@/components/charts/EChart.vue'
import Notice from '@/components/Notice.vue'
import {
  ACCION, caudalNodo, claveSospechoso, claveTendencia, flujoDe, leyIdx, nombreVariable, toleranciasAmplias,
  type Equivalente, type SerieVar, type Sospechoso,
} from '@/domain/diagnostico'
import { flowAdjustments, NEAR_LIMIT_PCT } from '@/domain/reconciliationQuality'
import type { Sesion } from '@/domain/types'
import { fmt } from '@/lib/format'
import { useSesionStore } from '@/stores/sesion'

const props = defineProps<{ sesion: Sesion; editable: boolean }>()
const emit = defineEmits<{ ver: [] }>()
const store = useSesionStore()

// Loads on open and again whenever the stored session changes (a run, a restore, a save).
onMounted(() => store.cargarDiagnostico())
watch(() => props.sesion.updated_at, () => store.cargarDiagnostico())

const d = computed(() => store.diag?.diagnostico ?? null)
const corrida = computed(() => props.sesion.corrida || !!props.sesion.balance)
const nombreVar = (v: Equivalente['variable']) => nombreVariable(v, props.sesion.componentes)
const fila = (e: Pick<Equivalente, 'flujo' | 'tag'>) => flujoDe(props.sesion.flujos, e.flujo, e.tag)
const unidad = (v: Equivalente['variable']) => (leyIdx(v) < 0 ? 't' : '%')
const dec = (v: Equivalente['variable']) => (leyIdx(v) < 0 ? 0 : 2)

// ── Global test ─────────────────────────────────────────────────────────────
const finos = computed(() => (d.value?.por_componente ?? []).map(c => ({ nombre: props.sesion.componentes[c.componente_idx] ?? `Ley ${c.componente_idx + 1}`, pasa: c.global.pasa })))

// ── Suspects ────────────────────────────────────────────────────────────────
const verDescartados = ref(false)
const sospechosos = computed(() => (d.value?.sospechosos ?? []).filter(s => verDescartados.value || !store.descartados.has(claveSospechoso(s))))
const nDescartados = computed(() => (d.value?.sospechosos ?? []).filter(s => store.descartados.has(claveSospechoso(s))).length)
const zMax = computed(() => Math.max(1, ...(d.value?.sospechosos ?? []).map(s => Math.abs(s.z ?? 0))))
const bloqueado = computed(() => !!store.busy)

function verEnDiagrama(e: Equivalente) {
  const f = fila(e)
  if (!f) return
  store.overlaySospechosos = true
  store.flujoPulso = f.idx
  emit('ver')
}
function aplicar(s: Sospechoso, e: Equivalente) {
  const f = fila(e)
  // An equivalent only carries a value: widening applies to the flagged measurement itself.
  if (f && e.valor_sugerido !== null) store.aplicarSugerencia(f.idx, e.variable, e === s ? s.accion : 'corregir_valor', e.valor_sugerido)
}
function descartar(s: Sospechoso) {
  const k = claveSospechoso(s)
  if (store.descartados.has(k)) store.descartados.delete(k)
  else store.descartados.add(k)
}

function tendencia(s: Pick<Equivalente, 'flujo' | 'tag' | 'variable'>): SerieVar | null {
  const t = store.diag?.tendencia[claveTendencia(s)]
  const k = leyIdx(s.variable)
  const v = !t ? null : k < 0 ? t : (t.leyes[k] ?? null)
  return v && v.serie.length >= 2 ? v : null
}
const sparkline = (v: SerieVar) => (t: Tokens) => ({
  grid: { left: 2, right: 2, top: 4, bottom: 4 },
  xAxis: { type: 'category', show: false, data: v.serie.map(p => p.periodo ?? `#${p.balance_id}`) },
  yAxis: { type: 'value', show: false, scale: true },
  tooltip: { trigger: 'axis', valueFormatter: (x: number) => `${fmt(x, 2)} %` },
  series: [{
    type: 'line', data: v.serie.map(p => p.ajuste_pct), symbolSize: 4, lineStyle: { width: 1.5, color: v.sesgo_recurrente ? t.danger : t.primary },
    itemStyle: { color: v.sesgo_recurrente ? t.danger : t.primary },
    markLine: { silent: true, symbol: 'none', label: { show: false }, lineStyle: { color: t.inkFaint, type: 'dashed', width: 1 }, data: [{ yAxis: 0 }] },
  }],
})
const resumenTendencia = (v: SerieVar) => {
  const ult = v.serie.at(-1)!
  return `Ajuste de la reconciliación en los últimos ${v.serie.length} balances guardados; el último, ${fmt(ult.ajuste_pct, 2)} %.`
    + (v.sesgo_recurrente ? ` Corrige siempre hacia ${v.signo === 1 ? 'arriba' : 'abajo'}: posible sesgo del medidor.` : '')
}

// ── Wide tolerances ─────────────────────────────────────────────────────────
const amplias = computed(() => toleranciasAmplias(props.sesion))

// ── Charts ──────────────────────────────────────────────────────────────────
const desbalanceEn = ref<'unidades' | 'pct'>('unidades')
const nodos = computed(() => (d.value?.nodos ?? []).map(n => {
  const nombre = typeof n.nodo === 'number' ? (props.sesion.nodos[n.nodo]?.nombre ?? `Nodo ${n.nodo + 1}`) : n.nodo
  const caudal = caudalNodo(props.sesion, n.nodo)
  return { nombre, sospechoso: n.sospechoso, z: n.z, t: n.residuo ?? 0, pct: caudal && n.residuo !== null ? (n.residuo / caudal) * 100 : null }
}))
const resumenNodos = computed(() => {
  const n = nodos.value
  const malos = n.filter(x => x.sospechoso)
  const peor = [...n].sort((a, b) => Math.abs(b.z ?? 0) - Math.abs(a.z ?? 0))[0]
  if (!n.length) return 'No hay nodos con mediciones para comparar.'
  return malos.length
    ? `${malos.length} de ${n.length} nodos no ${malos.length === 1 ? 'cierra' : 'cierran'} dentro de lo que explican sus tolerancias; el más desbalanceado es ${peor!.nombre} (${fmt(peor!.t, 0)} t).`
    : `Los ${n.length} nodos cierran dentro de lo que explican sus tolerancias.`
})
const opcionNodos = (t: Tokens) => {
  const pct = desbalanceEn.value === 'pct'
  return {
    grid: { left: 8, right: 8, top: 12, bottom: 8, containLabel: true },
    xAxis: { type: 'category', data: nodos.value.map(n => n.nombre), axisLabel: { color: t.inkMuted, rotate: nodos.value.length > 8 ? 35 : 0, fontSize: 11 }, axisLine: { lineStyle: { color: t.line } } },
    yAxis: { type: 'value', name: pct ? '%' : 't', nameTextStyle: { color: t.inkFaint }, axisLabel: { color: t.inkMuted, formatter: (x: number) => fmt(x, pct ? 2 : 0) }, splitLine: { lineStyle: { color: t.line } } },
    tooltip: {
      trigger: 'axis', axisPointer: { type: 'shadow' },
      formatter: (ps: { dataIndex: number }[]) => {
        const n = nodos.value[ps[0]!.dataIndex]!
        return `<b>${n.nombre}</b><br>Desbalance medido: ${fmt(n.t, 0)} t${n.pct === null ? '' : ` (${fmt(n.pct, 2)} %)`}<br>${n.sospechoso ? 'No cierra con sus tolerancias' : 'Cierra con sus tolerancias'}`
      },
    },
    series: [{
      type: 'bar', barMaxWidth: 28,
      data: nodos.value.map(n => ({ value: pct ? n.pct : n.t, itemStyle: { color: n.sospechoso ? t.danger : t.primary, borderRadius: 2 } })),
    }],
  }
}

const usos = computed(() =>
  flowAdjustments(props.sesion.flujos, props.sesion.componentes)
    .filter(a => a.usoPct !== null)
    .sort((a, b) => b.usoPct! - a.usoPct!)
    .slice(0, 20))
const resumenUso = computed(() => {
  const todos = flowAdjustments(props.sesion.flujos, props.sesion.componentes)
  const fuera = todos.filter(a => a.estado === 'fuera').length
  const limite = todos.filter(a => a.estado === 'limite').length
  if (!fuera && !limite) return 'Todas las mediciones quedaron bajo el 80 % de su tolerancia.'
  return `${fuera} ${fuera === 1 ? 'medición superó' : 'mediciones superaron'} su tolerancia y ${limite} ${limite === 1 ? 'quedó' : 'quedaron'} sobre el 80 %. Se muestran las ${usos.value.length} con mayor uso.`
})
const opcionUso = (t: Tokens) => {
  const rows = [...usos.value].reverse() // ECharts draws category 0 at the bottom
  return {
    grid: { left: 8, right: 24, top: 8, bottom: 28, containLabel: true },
    xAxis: { type: 'value', name: '% de la tolerancia', nameLocation: 'middle', nameGap: 26, nameTextStyle: { color: t.inkFaint }, axisLabel: { color: t.inkMuted }, splitLine: { lineStyle: { color: t.line } } },
    yAxis: { type: 'category', data: rows.map(a => `${a.flujo} · ${a.variable}`), axisLabel: { color: t.inkMuted, fontSize: 11, width: 170, overflow: 'truncate' }, axisLine: { lineStyle: { color: t.line } } },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (x: number) => `${fmt(x, 0)} %` },
    series: [{
      type: 'bar', barMaxWidth: 14,
      data: rows.map(a => ({ value: a.usoPct, itemStyle: { color: a.estado === 'fuera' ? t.danger : a.estado === 'limite' ? t.warn : t.primary, borderRadius: 2 } })),
      markLine: {
        silent: true, symbol: 'none', label: { color: t.inkMuted, formatter: '{c} %' },
        data: [{ xAxis: NEAR_LIMIT_PCT, lineStyle: { color: t.warn, type: 'dashed' } }, { xAxis: 100, lineStyle: { color: t.danger, type: 'dashed' } }],
      },
    }],
  }
}

const seg = 'h-7 px-2 text-xs font-medium text-ink-muted hover:text-ink aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-sm rounded-[5px]'
const card = 'rounded-lg border border-line bg-surface p-4'
const graficoNodos = ref<InstanceType<typeof EChart>>()
const graficoUso = ref<InstanceType<typeof EChart>>()
defineExpose({ graficoNodos, graficoUso })
</script>

<template>
  <!-- Two columns on wide screens: the diagnosis, and a side slot the S4 assistant panel fills (empty = one column). -->
  <div class="grid gap-6" :class="{ 'xl:grid-cols-[minmax(0,1fr)_24rem]': $slots.asistente }">
    <section aria-label="Diagnóstico de errores gruesos" class="min-w-0 space-y-6">
      <Notice v-if="sesion.solver?.ok === false" tone="danger">
        <b class="font-semibold">El solver no convergió en la última corrida.</b>
        Los valores reconciliados quedaron en los límites de sus tolerancias y no son confiables. Revisa los sospechosos de abajo y vuelve a correr.
        <span v-if="sesion.solver.mensaje" class="block text-ink-muted">Detalle: {{ sesion.solver.mensaje }}<template v-if="sesion.solver.iteraciones"> ({{ sesion.solver.iteraciones }} iteraciones)</template></span>
      </Notice>
      <Notice v-if="store.dirty" tone="info">El diagnóstico usa los datos guardados. Tus cambios sin correr no están incluidos: corre el balance para actualizarlo.</Notice>

      <div v-if="store.diagCargando && !d" class="grid min-h-48 place-items-center text-ink-muted" role="status">
        <span class="flex items-center gap-2"><LoaderCircle class="size-4 animate-spin" aria-hidden="true" /> Calculando el diagnóstico…</span>
      </div>
      <div v-else-if="store.diagError && !d" class="space-y-3">
        <Notice tone="danger">{{ store.diagError }}</Notice>
        <button type="button" class="btn btn-quiet h-9 px-3" @click="store.cargarDiagnostico()"><RotateCcw class="size-4" aria-hidden="true" /> Reintentar</button>
      </div>

      <template v-else-if="d">
        <p v-if="store.diagCargando" class="flex items-center gap-2 text-xs text-ink-muted" role="status"><LoaderCircle class="size-3.5 animate-spin" aria-hidden="true" /> Actualizando el diagnóstico…</p>
        <Notice v-else-if="store.diagError" tone="danger">{{ store.diagError }} Se muestra el diagnóstico anterior.</Notice>

        <!-- Global status -->
        <div :class="[card, d.global.pasa ? 'border-l-4 border-l-ok' : 'border-l-4 border-l-danger']" role="status">
          <div class="flex items-start gap-3">
            <CircleCheck v-if="d.global.pasa" class="mt-0.5 size-5 shrink-0 text-ok" aria-hidden="true" />
            <CircleAlert v-else class="mt-0.5 size-5 shrink-0 text-danger" aria-hidden="true" />
            <div class="min-w-0 space-y-1.5">
              <h2 class="font-display text-base font-semibold">{{ d.global.pasa ? 'El balance es consistente' : 'Hay mediciones sospechosas' }}</h2>
              <p class="text-[0.8125rem] text-ink-muted">
                <template v-if="d.global.pasa">Las diferencias entre entradas y salidas de los nodos caben dentro de lo que permiten las tolerancias de las mediciones.</template>
                <template v-else>Las diferencias entre entradas y salidas de los nodos son mayores de lo que permiten las tolerancias: alguna medición probablemente tiene un error grueso.</template>
                El indicador de desbalance
                (<abbr title="Prueba global (chi-cuadrado): suma de los desbalances de todos los nodos, cada uno dividido por lo que sus tolerancias permiten. Es cero si todo cierra exactamente.">γ</abbr>)
                vale <b class="font-semibold text-ink">{{ fmt(d.global.gamma, 1) }}</b> y el máximo aceptable es
                <b class="font-semibold text-ink"><abbr title="Valor crítico al 95 % de confianza para los grados de libertad del balance: por debajo, el desbalance se explica por el error normal de medición.">{{ fmt(d.global.critico, 1) }}</abbr></b>.
                <template v-if="!d.global.pasa && d.sospechosos.length">
                  {{ d.global_final.pasa ? `Corrigiendo ${d.sospechosos.filter(s => s.variable === 'tms').length === 1 ? 'la medición marcada' : 'las mediciones marcadas'} el balance pasaría la prueba.` : 'Aun quitando las mediciones marcadas el balance no pasa: revisa también la topología y las tolerancias.' }}
                </template>
              </p>
              <ul v-if="finos.length" class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                <li v-for="c in finos" :key="c.nombre" class="flex items-center gap-1.5">
                  <span class="size-1.5 rounded-full" :class="c.pasa ? 'bg-ok' : 'bg-danger'" aria-hidden="true" />
                  Finos de {{ c.nombre }}: {{ c.pasa ? 'consistentes' : 'con sospechosos' }}
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Ranked suspects -->
        <section aria-labelledby="sosp-h" class="space-y-3">
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="sosp-h" class="font-display text-base font-semibold">Mediciones sospechosas</h2>
            <button v-if="nDescartados" type="button" class="text-xs font-medium text-primary hover:underline" @click="verDescartados = !verDescartados">
              {{ verDescartados ? 'Ocultar descartadas' : `Mostrar ${nDescartados} descartada${nDescartados === 1 ? '' : 's'}` }}
            </button>
          </div>
          <p v-if="!d.sospechosos.length" class="text-[0.8125rem] text-ink-muted">Ninguna medición se aparta más de lo que su tolerancia permite.</p>
          <p v-else-if="!sospechosos.length" class="text-[0.8125rem] text-ink-muted">Descartaste todas las sospechosas en esta visita.</p>
          <p v-else class="text-xs text-ink-muted">
            Ordenadas de más a menos sospechosa.
            <abbr title="Cuántas desviaciones estándar se aparta la medición de lo que exige el balance. Sobre ~3 es muy improbable que sea error normal.">|z|</abbr>
            mide qué tan fuera de lo normal está cada una.
          </p>

          <ol class="space-y-3">
            <li v-for="(s, i) in sospechosos" :key="claveSospechoso(s)" :class="[card, { 'opacity-60': store.descartados.has(claveSospechoso(s)) }]">
              <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div class="min-w-0">
                  <p class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-display text-sm font-semibold text-ink-faint">{{ i + 1 }}.</span>
                    <span class="font-semibold">{{ s.flujo }}</span>
                    <span v-if="s.tag" class="text-xs text-ink-muted">{{ s.tag }}</span>
                    <span class="rounded bg-surface-2 px-1.5 py-0.5 text-[0.6875rem] font-semibold">{{ nombreVar(s.variable) }}</span>
                  </p>
                  <p class="mt-1 text-[0.8125rem]">
                    <span class="text-ink-muted">Medido</span> {{ fmt(s.valor_medido, dec(s.variable)) }} {{ unidad(s.variable) }}
                    <span aria-hidden="true" class="text-ink-faint">→</span><span class="sr-only">, sugerido</span>
                    <b class="font-semibold">{{ fmt(s.valor_sugerido, dec(s.variable)) }} {{ unidad(s.variable) }}</b>
                  </p>
                  <p class="mt-0.5 text-[0.8125rem] font-medium" :class="s.accion === 'corregir_valor' ? 'text-danger' : s.accion === 'ampliar_tolerancia' ? 'text-warn' : 'text-ink'">{{ ACCION[s.accion] }}</p>
                </div>
                <div class="w-40 shrink-0">
                  <p class="flex justify-between text-xs text-ink-muted"><span>|z|</span><span class="font-semibold text-ink">{{ fmt(Math.abs(s.z ?? 0), 1) }}</span></p>
                  <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-2" role="meter" :aria-valuenow="Math.abs(s.z ?? 0)" aria-valuemin="0" :aria-valuemax="zMax" :aria-label="`|z| de ${s.flujo}`">
                    <div class="h-full rounded-full bg-danger" :style="{ width: `${(Math.abs(s.z ?? 0) / zMax) * 100}%` }" />
                  </div>
                  <EChart
                    v-if="tendencia(s)" class="mt-2" compacto alto="2.25rem"
                    :titulo="`Tendencia de ${s.flujo} ${nombreVar(s.variable)}`" :resumen="resumenTendencia(tendencia(s)!)" :option="sparkline(tendencia(s)!)"
                  />
                  <p v-if="tendencia(s)?.sesgo_recurrente" class="mt-1 text-[0.6875rem] font-semibold text-danger" :title="resumenTendencia(tendencia(s)!)">Sesgo recurrente</p>
                </div>
              </div>

              <!-- Equivalent set: the data cannot tell which one is wrong. -->
              <div v-if="s.equivalentes.length" class="mt-3 rounded-md border border-dashed border-line-strong p-3">
                <p class="text-xs font-medium">El error está en uno de estos flujos; los datos no permiten distinguirlos.</p>
                <ul class="mt-2 divide-y divide-line">
                  <li v-for="e in [s, ...s.equivalentes]" :key="claveSospechoso(e)" class="flex flex-wrap items-center justify-between gap-2 py-1.5 text-[0.8125rem]">
                    <span>{{ e.flujo }} <span class="text-ink-muted">· sugerido {{ fmt(e.valor_sugerido, dec(e.variable)) }} {{ unidad(e.variable) }}</span></span>
                    <span class="flex gap-1">
                      <button type="button" class="btn btn-quiet h-7 px-2 text-xs" :disabled="!fila(e)" @click="verEnDiagrama(e)"><MapPin class="size-3.5" aria-hidden="true" /> Ver</button>
                      <button v-if="editable" type="button" class="btn btn-quiet h-7 px-2 text-xs" :disabled="!fila(e) || bloqueado || e.valor_sugerido === null" @click="aplicar(s, e)"><Wand2 class="size-3.5" aria-hidden="true" /> Aplicar</button>
                    </span>
                  </li>
                </ul>
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="btn btn-quiet h-8 px-3 text-[0.8125rem]" :disabled="!fila(s)" @click="verEnDiagrama(s)"><MapPin class="size-4" aria-hidden="true" /> Ver en diagrama</button>
                <button
                  v-if="editable && !s.equivalentes.length" type="button" class="btn btn-primary h-8 px-3 text-[0.8125rem]"
                  :disabled="!fila(s) || bloqueado || s.valor_sugerido === null" @click="aplicar(s, s)"
                >
                  <Wand2 class="size-4" aria-hidden="true" /> Aplicar sugerencia
                </button>
                <button type="button" class="btn btn-quiet h-8 px-3 text-[0.8125rem]" @click="descartar(s)">
                  <EyeOff class="size-4" aria-hidden="true" /> {{ store.descartados.has(claveSospechoso(s)) ? 'Restaurar' : 'Descartar' }}
                </button>
              </div>
              <p v-if="!fila(s)" class="mt-2 text-xs text-ink-muted">Este flujo no está en la tabla de la sesión.</p>
            </li>
          </ol>
        </section>

        <!-- Charts -->
        <div class="grid gap-4 lg:grid-cols-2">
          <div :class="card" class="relative">
            <div class="absolute top-3 right-3">
              <div class="flex items-center gap-0.5 rounded-md bg-surface-2 p-0.5" role="group" aria-label="Unidades del desbalance">
                <button type="button" :class="seg" :aria-pressed="desbalanceEn === 'unidades'" @click="desbalanceEn = 'unidades'">t</button>
                <button type="button" :class="seg" :aria-pressed="desbalanceEn === 'pct'" @click="desbalanceEn = 'pct'">%</button>
              </div>
            </div>
            <EChart ref="graficoNodos" class="[&_figcaption]:pr-16" titulo="Desbalance medido por nodo" :resumen="resumenNodos" :option="opcionNodos" />
          </div>
          <div :class="card">
            <EChart
              v-if="corrida && usos.length" ref="graficoUso" titulo="Uso de tolerancia por medición" :resumen="resumenUso" :option="opcionUso"
              :alto="`${Math.max(12, usos.length * 1.4 + 3)}rem`"
            />
            <template v-else>
              <h3 class="text-sm font-semibold">Uso de tolerancia por medición</h3>
              <p class="mt-1 text-xs text-ink-muted">{{ corrida ? 'Ninguna medición tiene una tolerancia acotada.' : 'Corre el balance para ver cuánto de su tolerancia usó cada medición.' }}</p>
            </template>
          </div>
        </div>

        <!-- Tolerances that hide errors -->
        <section aria-labelledby="tol-h" :class="card">
          <h2 id="tol-h" class="font-display text-base font-semibold">Tolerancias que ocultan errores</h2>
          <p class="mt-1 text-xs text-ink-muted">
            Con una tolerancia sobre {{ fmt(amplias.tolMax, 1) }} %, un error de 25 % en la medición no se distingue del error normal y el diagnóstico no lo puede marcar.
            Es una estimación con la tolerancia sola: según cómo esté conectado el flujo, el límite real puede ser aún menor.
          </p>
          <p v-if="!amplias.flujos.length" class="mt-2 text-[0.8125rem]">Ninguna tolerancia es tan amplia.</p>
          <ul v-else class="mt-2 grid gap-x-6 gap-y-1 text-[0.8125rem] sm:grid-cols-2">
            <li v-for="t in amplias.flujos" :key="`${t.idx}-${t.variable}`" class="flex justify-between gap-3 border-b border-line py-1">
              <span class="min-w-0 truncate">{{ t.flujo }} <span class="text-ink-muted">· {{ t.variable }}</span></span>
              <span class="shrink-0 font-semibold text-warn">± {{ fmt(t.tolPct, 1) }} %</span>
            </li>
          </ul>
          <p v-if="d.no_redundantes.length" class="mt-3 text-xs text-ink-muted">
            <b class="font-semibold text-ink">Sin verificación posible:</b> {{ d.no_redundantes.join(', ') }}.
            Ninguna otra medición las controla, así que cualquier error en ellas pasa al resultado sin detectarse.
          </p>
        </section>
      </template>
    </section>

    <!-- S4: assistant panel slot (renders nothing until S4 fills it). -->
    <aside v-if="$slots.asistente" aria-label="Asistente" class="min-w-0"><slot name="asistente" /></aside>
  </div>
</template>
