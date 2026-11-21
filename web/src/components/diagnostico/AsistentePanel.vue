<script setup lang="ts">
import { Check, LoaderCircle, MapPin, RotateCcw, SendHorizontal, Sparkles, Wand2 } from '@lucide/vue'
import { computed, nextTick, ref, watch } from 'vue'
import Notice from '@/components/Notice.vue'
import { bloques, edicionDePropuesta, flujoDePropuesta, type Edicion, type Propuesta } from '@/domain/asistente'
import { ACCION, leyIdx, nombreVariable } from '@/domain/diagnostico'
import type { Sesion } from '@/domain/types'
import { fmt } from '@/lib/format'
import { useSesionStore } from '@/stores/sesion'

const props = defineProps<{ sesion: Sesion; editable: boolean }>()
const emit = defineEmits<{ ver: [] }>()
const store = useSesionStore()

const texto = ref('')
const lista = ref<HTMLElement>()

// "¿Por qué no cierra el nodo X?" names the worst node the diagnosis found, when there is one.
const peorNodo = computed(() => {
  const n = [...(store.diag?.diagnostico.nodos ?? [])].filter(x => x.sospechoso).sort((a, b) => Math.abs(b.z ?? 0) - Math.abs(a.z ?? 0))[0]
  if (!n) return null
  return typeof n.nodo === 'number' ? (props.sesion.nodos[n.nodo]?.nombre ?? null) : n.nodo
})
const sugeridas = computed(() => [
  '¿Qué medición reviso primero?',
  peorNodo.value ? `¿Por qué no cierra el nodo ${peorNodo.value}?` : '¿Por qué no cierra el balance?',
  '¿Qué tolerancias debería ajustar?',
])

function enviar(t = texto.value) {
  const q = t.trim()
  if (!q || store.chatCargando) return
  texto.value = ''
  store.preguntarAsistente(q)
}
function teclas(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
    e.preventDefault()
    enviar()
  }
}

// Keep the newest message in view.
watch(() => [store.chat.length, store.chatCargando, store.chatError], () => nextTick(() => lista.value?.scrollTo({ top: lista.value.scrollHeight })))

const unidad = (p: Propuesta) => (leyIdx(p.variable) < 0 ? 't' : '%')
const dec = (p: Propuesta) => (leyIdx(p.variable) < 0 ? 0 : 2)
const calculo = (p: Propuesta) => edicionDePropuesta(p, props.sesion, store.diag?.diagnostico.sospechosos)
const edicion = (p: Propuesta): Edicion | null => { const e = calculo(p); return e && !('error' in e) ? e : null }
const fueraDeRango = (p: Propuesta) => { const e = calculo(p); return e && 'error' in e ? e.error : null }

async function aplicar(p: Propuesta) {
  const e = edicion(p)
  // Same path as "Aplicar sugerencia": ownership question, dirty flag, no run.
  if (e && (await store.aplicarSugerencia(e.flujo, e.variable, e.accion, e.sugerido))) p.aplicada = true
}
function ver(p: Propuesta) {
  const f = flujoDePropuesta(p, props.sesion.flujos)
  if (!f) return
  store.overlaySospechosos = true
  store.flujoPulso = f.idx
  emit('ver')
}
</script>

<template>
  <section aria-labelledby="asis-h" class="flex flex-col rounded-lg border border-line bg-surface xl:sticky xl:top-4 xl:max-h-[calc(100dvh-2rem)]">
    <header class="border-b border-line px-4 py-3">
      <h2 id="asis-h" class="flex items-center gap-2 font-display text-base font-semibold">
        <Sparkles class="size-4 text-primary" aria-hidden="true" /> Asistente de balance
      </h2>
      <p class="mt-1 text-xs text-ink-muted">Sugerencias generadas por IA a partir del diagnóstico; verifica antes de aplicar.</p>
    </header>

    <div ref="lista" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4" aria-live="polite" aria-relevant="additions">
      <div v-if="!store.chat.length" class="space-y-2">
        <p class="text-[0.8125rem] text-ink-muted">Pregunta sobre las mediciones sospechosas, los nodos que no cierran o las tolerancias de esta sesión.</p>
        <ul class="flex flex-col items-start gap-1.5">
          <li v-for="q in sugeridas" :key="q">
            <button type="button" class="rounded-full border border-line px-3 py-1 text-left text-[0.8125rem] hover:border-primary hover:text-primary disabled:opacity-60" :disabled="store.chatCargando" @click="enviar(q)">{{ q }}</button>
          </li>
        </ul>
      </div>

      <article v-for="(m, i) in store.chat" :key="i" :aria-label="m.rol === 'usuario' ? 'Tu pregunta' : 'Respuesta del asistente'">
        <p v-if="m.rol === 'usuario'" class="ml-6 rounded-lg bg-primary-soft px-3 py-2 text-[0.8125rem] whitespace-pre-wrap">{{ m.texto }}</p>
        <div v-else class="space-y-3">
          <div class="space-y-2 text-[0.8125rem] leading-relaxed">
            <template v-for="(b, j) in bloques(m.texto)" :key="j">
              <p v-if="b.tipo === 'p'">{{ b.texto }}</p>
              <component :is="b.tipo" v-else class="space-y-1 pl-5" :class="b.tipo === 'ul' ? 'list-disc' : 'list-decimal'">
                <li v-for="(it, k) in b.items" :key="k">{{ it }}</li>
              </component>
            </template>
          </div>

          <ul v-if="m.propuestas?.length" class="space-y-2" aria-label="Propuestas">
            <li v-for="(p, j) in m.propuestas" :key="j" class="rounded-md border border-line-strong p-3">
              <p class="flex flex-wrap items-baseline gap-x-2">
                <span class="font-semibold">{{ p.flujo }}</span>
                <span class="rounded bg-surface-2 px-1.5 py-0.5 text-[0.6875rem] font-semibold">{{ nombreVariable(p.variable, sesion.componentes) }}</span>
              </p>
              <p class="mt-1 text-[0.8125rem] font-medium" :class="p.tipo === 'corregir_valor' ? 'text-danger' : p.tipo === 'ampliar_tolerancia' ? 'text-warn' : 'text-ink'">{{ ACCION[p.tipo] }}</p>
              <p v-if="edicion(p)" class="mt-0.5 text-[0.8125rem]">
                <template v-if="edicion(p)!.campo === 'medido'">
                  <span class="text-ink-muted">Medido</span> {{ fmt(edicion(p)!.antes, dec(p)) }} {{ unidad(p) }}
                  <span aria-hidden="true" class="text-ink-faint">→</span><span class="sr-only">, propuesto</span>
                  <b class="font-semibold">{{ fmt(edicion(p)!.despues, dec(p)) }} {{ unidad(p) }}</b>
                </template>
                <template v-else>
                  <span class="text-ink-muted">Tolerancia</span> ± {{ fmt(edicion(p)!.antes, 1) }} %
                  <span aria-hidden="true" class="text-ink-faint">→</span><span class="sr-only">, propuesta</span>
                  <b class="font-semibold">± {{ fmt(edicion(p)!.despues, 1) }} %</b>
                  <span class="text-ink-muted"> (para alcanzar {{ fmt(p.valor, dec(p)) }} {{ unidad(p) }})</span>
                </template>
              </p>
              <p v-else-if="p.valor !== null" class="mt-0.5 text-[0.8125rem]"><span class="text-ink-muted">Valor</span> {{ fmt(p.valor, dec(p)) }} {{ unidad(p) }}</p>
              <p class="mt-1.5 text-xs text-ink-muted">{{ p.motivo }}</p>
              <p v-if="fueraDeRango(p)" class="mt-1 text-xs font-medium text-warn">No se puede aplicar: {{ fueraDeRango(p) }}</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" class="btn btn-quiet h-7 px-2 text-xs" :disabled="!flujoDePropuesta(p, sesion.flujos)" @click="ver(p)"><MapPin class="size-3.5" aria-hidden="true" /> Ver en diagrama</button>
                <template v-if="editable && edicion(p)">
                  <span v-if="p.aplicada" class="flex items-center gap-1 text-xs font-medium text-ok"><Check class="size-3.5" aria-hidden="true" /> Aplicada</span>
                  <button v-else type="button" class="btn btn-primary h-7 px-2 text-xs" :disabled="!!store.busy" @click="aplicar(p)"><Wand2 class="size-3.5" aria-hidden="true" /> Aplicar</button>
                </template>
              </div>
            </li>
          </ul>
        </div>
      </article>

      <p v-if="store.chatCargando" class="flex items-center gap-2 text-xs text-ink-muted" role="status"><LoaderCircle class="size-3.5 animate-spin" aria-hidden="true" /> El asistente está revisando el diagnóstico…</p>
      <div v-else-if="store.chatError" class="space-y-2">
        <Notice tone="danger">{{ store.chatError }}</Notice>
        <button type="button" class="btn btn-quiet h-8 px-3 text-[0.8125rem]" @click="store.preguntarAsistente()"><RotateCcw class="size-4" aria-hidden="true" /> Reintentar</button>
      </div>
    </div>

    <form class="flex items-end gap-2 border-t border-line p-3" @submit.prevent="enviar()">
      <label for="asis-input" class="sr-only">Pregunta al asistente</label>
      <textarea
        id="asis-input" v-model="texto" rows="2" maxlength="4000" placeholder="Escribe tu pregunta…"
        class="field min-h-10 flex-1 resize-none py-2 text-[0.8125rem]" @keydown="teclas"
      />
      <button type="submit" class="btn btn-primary size-10 shrink-0 p-0" :disabled="!texto.trim() || store.chatCargando" aria-label="Enviar pregunta">
        <SendHorizontal class="size-4" aria-hidden="true" />
      </button>
    </form>
  </section>
</template>
