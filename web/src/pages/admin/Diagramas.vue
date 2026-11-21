<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue'
import { onMounted, ref } from 'vue'
import Notice from '@/components/Notice.vue'
import { api, errorMessage } from '@/lib/api'
import { fecha, valleColor } from '@/lib/format'

interface Fila {
  id: number
  nombre: string
  valle: string | null
  guardado: boolean
  updated_at: string | null
  usuario: string | null
}

const filas = ref<Fila[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    filas.value = (await api<{ procesos: Fila[] }>('/v2/diagramas')).procesos
  } catch (e) {
    error.value = errorMessage(e, 'No se pudo cargar la lista de procesos.')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="mx-auto max-w-4xl">
    <h1 class="font-display text-xl font-semibold">Diagramas de proceso</h1>
    <p class="mt-1 max-w-2xl text-ink-muted">
      Cada proceso se dibuja automáticamente. Para fijar una disposición, abre un balance del proceso, arrastra los nodos en la pestaña
      Diagrama y pulsa «Guardar disposición»; se usará en todos sus balances.
    </p>

    <Notice v-if="error" tone="danger" class="mt-4">{{ error }}</Notice>
    <div v-else-if="loading" class="mt-6 flex items-center gap-2 text-ink-muted" role="status">
      <LoaderCircle class="size-4 animate-spin" aria-hidden="true" /> Cargando procesos…
    </div>
    <div v-else class="mt-6 overflow-x-auto rounded-lg border border-line bg-surface">
      <table class="w-full min-w-[34rem] text-left text-[0.8125rem]">
        <thead class="border-b border-line text-xs text-ink-muted">
          <tr>
            <th scope="col" class="px-4 py-2.5 font-medium">Proceso</th>
            <th scope="col" class="px-4 py-2.5 font-medium">Disposición</th>
            <th scope="col" class="px-4 py-2.5 font-medium">Última modificación</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in filas" :key="p.id" class="border-b border-line last:border-0">
            <td class="px-4 py-2.5">
              <span class="flex items-center gap-2">
                <span class="size-2 shrink-0 rounded-full" :style="{ background: valleColor(p.valle) }" aria-hidden="true" />
                <span><span class="text-ink-muted">{{ p.valle }} ·</span> {{ p.nombre }}</span>
              </span>
            </td>
            <td class="px-4 py-2.5">
              <span v-if="p.guardado" class="rounded bg-ok-soft px-1.5 py-0.5 text-[0.6875rem] font-semibold text-ok">Guardada</span>
              <span v-else class="rounded bg-surface-2 px-1.5 py-0.5 text-[0.6875rem] font-semibold text-ink-muted">Automática</span>
            </td>
            <td class="px-4 py-2.5 text-ink-muted">
              <template v-if="p.guardado">{{ fecha(p.updated_at, true) }}<template v-if="p.usuario"> · {{ p.usuario }}</template></template>
              <template v-else>—</template>
            </td>
          </tr>
          <tr v-if="!filas.length">
            <td colspan="3" class="px-4 py-6 text-center text-ink-muted">No hay procesos.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
