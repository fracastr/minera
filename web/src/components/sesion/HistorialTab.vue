<script setup lang="ts">
import { History, RotateCcw } from '@lucide/vue'
import { onMounted, ref, watch } from 'vue'
import Notice from '@/components/Notice.vue'
import type { Corrida } from '@/domain/types'
import { api, errorMessage } from '@/lib/api'
import { ask } from '@/lib/feedback'
import { fecha } from '@/lib/format'
import { useSesionStore } from '@/stores/sesion'

const props = defineProps<{ sesionId: number; version: string | null; editable: boolean }>()
const store = useSesionStore()

const corridas = ref<Corrida[]>([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  try {
    corridas.value = (await api<{ corridas: Corrida[] }>(`/v2/sesiones/${props.sesionId}/corridas`)).corridas
    error.value = ''
  } catch (e) {
    error.value = errorMessage(e, 'No se pudo cargar el historial.')
  } finally {
    loading.value = false
  }
}
onMounted(load)
// Any change to the session (run, restore, clone) adds a version.
watch(() => [props.sesionId, props.version], load)

const tipo = { importacion: 'Importación', corrida: 'Corrida', restauracion: 'Restauración' }

async function restaurar(c: Corrida) {
  const ok = await ask(
    'Restaurar esta versión',
    `Los datos de la sesión vuelven a como estaban en la ${tipo[c.tipo].toLowerCase()} del ${fecha(c.created_at, true)}; se registra como una nueva versión y puedes volver a la actual desde este historial.`,
    [{ value: 'si', label: 'Restaurar' }],
  )
  if (ok && (await store.restaurar(c.id))) load()
}
</script>

<template>
  <section aria-labelledby="t-hist" class="max-w-3xl">
    <h2 id="t-hist" class="font-display text-[0.9375rem] font-semibold">Historial de versiones</h2>
    <p class="mt-1 text-ink-muted">Cada importación, corrida y restauración guarda una versión de los datos.</p>
    <Notice v-if="error" tone="danger" class="mt-4">{{ error }}</Notice>
    <p v-else-if="loading && !corridas.length" class="mt-6 text-ink-muted">Cargando…</p>
    <div v-else-if="!corridas.length" class="mt-6 flex items-center gap-3 rounded-lg border border-dashed border-line-strong p-6 text-ink-muted">
      <History class="size-5" aria-hidden="true" /> Esta sesión no tiene versiones registradas (se guardó antes de que existiera el historial).
    </div>
    <ol v-else class="mt-4 divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
      <li v-for="c in corridas" :key="c.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3">
        <span class="size-2 shrink-0 rounded-full" :class="c.tipo === 'corrida' ? 'bg-primary' : c.tipo === 'restauracion' ? 'bg-warn' : 'bg-ink-faint'" aria-hidden="true" />
        <div class="min-w-0 flex-1">
          <p class="font-medium">
            {{ tipo[c.tipo] }}
            <span v-if="c.actual" class="ml-1.5 rounded bg-primary-soft px-1.5 py-0.5 text-[0.6875rem] font-medium text-primary">Actual</span>
          </p>
          <p class="text-xs text-ink-muted">{{ fecha(c.created_at, true) }} · {{ c.usuario?.nombre ?? 'Sin registrar' }}</p>
        </div>
        <button v-if="editable && !c.actual" type="button" class="btn btn-quiet h-8 px-3 text-[0.8125rem]" :disabled="!!store.busy" @click="restaurar(c)">
          <RotateCcw class="size-3.5" aria-hidden="true" /> Restaurar
        </button>
      </li>
    </ol>
  </section>
</template>
