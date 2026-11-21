<script setup lang="ts">
import { Check, FileSpreadsheet, LoaderCircle, Upload } from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Notice from '@/components/Notice.vue'
import type { Valle } from '@/domain/types'
import { api, errorMessage, upload } from '@/lib/api'
import { valleColor } from '@/lib/format'

const router = useRouter()
const valles = ref<Valle[]>([])
const valleId = ref<number | null>(null)
const procesoId = ref<number | null>(null)
const file = ref<File | null>(null)
const dragging = ref(false)
const progress = ref<number | null>(null)
const error = ref('')
const input = ref<HTMLInputElement>()

const valle = computed(() => valles.value.find(v => v.id === valleId.value))
const proceso = computed(() => valle.value?.procesos.find(p => p.id === procesoId.value))
const MAX_MB = 20

onMounted(async () => {
  try {
    valles.value = (await api<{ valles: Valle[] }>('/v2/catalogo')).valles
    if (valles.value.length === 1) valleId.value = valles.value[0]!.id
  } catch (e) {
    error.value = errorMessage(e, 'No se pudo cargar la lista de procesos.')
  }
})

function pickValle(id: number) {
  if (valleId.value !== id) procesoId.value = null
  valleId.value = id
}

function setFile(f: File | undefined) {
  error.value = ''
  if (!f) return
  if (!/\.xlsx?$/i.test(f.name)) return void (error.value = 'El archivo debe ser una planilla Excel (.xlsx o .xls).')
  if (f.size > MAX_MB * 1024 * 1024) return void (error.value = `El archivo supera los ${MAX_MB} MB.`)
  file.value = f
}

function onDrop(e: DragEvent) {
  dragging.value = false
  setFile(e.dataTransfer?.files[0])
}

async function importar() {
  if (!file.value || !procesoId.value) return
  const body = new FormData()
  body.append('proceso_id', String(procesoId.value))
  body.append('file', file.value)
  error.value = ''
  progress.value = 0
  try {
    const { id } = await upload<{ id: number }>('/v2/sesiones', body, p => (progress.value = p))
    await router.push({ name: 'balance-sesion', params: { id } })
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudo importar el archivo.'
    progress.value = null
  }
}

const uploading = computed(() => progress.value !== null)
const stepTitle = 'flex items-center gap-2.5 font-display text-[0.9375rem] font-semibold'
const stepNum = 'grid size-6 place-items-center rounded-full text-xs font-semibold'
</script>

<template>
  <div class="mx-auto max-w-4xl">
    <h1 class="font-display text-xl font-semibold">Nuevo balance</h1>
    <p class="mt-1 text-ink-muted">Elige el proceso, sube el Excel de carga y entra directo al espacio de balance.</p>

    <Notice v-if="error" tone="danger" class="mt-5">{{ error }}</Notice>

    <section class="mt-6" aria-labelledby="paso-valle">
      <h2 id="paso-valle" :class="stepTitle">
        <span :class="[stepNum, valle ? 'bg-primary text-primary-ink' : 'bg-surface-2 text-ink-muted']">
          <Check v-if="valle" class="size-3.5" aria-hidden="true" /><template v-else>1</template>
        </span>
        Valle
      </h2>
      <div class="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-labelledby="paso-valle">
        <button
          v-for="v in valles" :key="v.id" type="button" role="radio" :aria-checked="valleId === v.id"
          class="flex h-10 items-center gap-2 rounded-md border px-4 font-medium transition-colors"
          :class="valleId === v.id ? 'border-primary bg-primary-soft text-ink' : 'border-line-strong bg-surface hover:border-ink-faint'"
          :disabled="uploading"
          @click="pickValle(v.id)"
        >
          <span class="size-2.5 rounded-full" :style="{ background: valleColor(v.nombre) }" aria-hidden="true" />
          {{ v.nombre }}
        </button>
      </div>
    </section>

    <section v-if="valle" class="mt-7" aria-labelledby="paso-proceso">
      <h2 id="paso-proceso" :class="stepTitle">
        <span :class="[stepNum, proceso ? 'bg-primary text-primary-ink' : 'bg-surface-2 text-ink-muted']">
          <Check v-if="proceso" class="size-3.5" aria-hidden="true" /><template v-else>2</template>
        </span>
        Proceso
      </h2>
      <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="radiogroup" aria-labelledby="paso-proceso">
        <button
          v-for="p in valle.procesos" :key="p.id" type="button" role="radio" :aria-checked="procesoId === p.id"
          class="relative overflow-hidden rounded-lg border bg-surface p-4 pl-5 text-left transition-[border-color,box-shadow]"
          :class="procesoId === p.id ? 'border-primary shadow-[0_0_0_1px_var(--primary)]' : 'border-line hover:border-line-strong'"
          :disabled="uploading"
          @click="procesoId = p.id"
        >
          <span class="absolute inset-y-0 left-0 w-1" :style="{ background: valleColor(valle.nombre) }" aria-hidden="true" />
          <span class="block font-medium">{{ p.nombre }}</span>
          <span class="mt-1 block text-xs text-ink-muted">{{ p.componentes.join(' · ') || 'Sin componentes' }}</span>
        </button>
      </div>
    </section>

    <section v-if="proceso" class="mt-7" aria-labelledby="paso-excel">
      <h2 id="paso-excel" :class="stepTitle">
        <span :class="[stepNum, file ? 'bg-primary text-primary-ink' : 'bg-surface-2 text-ink-muted']">
          <Check v-if="file" class="size-3.5" aria-hidden="true" /><template v-else>3</template>
        </span>
        Excel de carga
      </h2>
      <div
        class="mt-3 flex flex-col items-center rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors"
        :class="dragging ? 'border-primary bg-primary-soft' : 'border-line-strong bg-surface'"
        @dragover.prevent="dragging = true"
        @dragleave="dragging = false"
        @drop.prevent="onDrop"
      >
        <template v-if="file">
          <FileSpreadsheet class="size-8 text-ok" stroke-width="1.5" aria-hidden="true" />
          <p class="mt-3 font-medium break-all">{{ file.name }}</p>
          <p class="text-xs text-ink-muted">{{ (file.size / 1024 / 1024).toFixed(2) }} MB</p>
          <button v-if="!uploading" type="button" class="mt-2 text-[0.8125rem] text-primary hover:underline" @click="input?.click()">Cambiar archivo</button>
        </template>
        <template v-else>
          <Upload class="size-8 text-ink-faint" stroke-width="1.5" aria-hidden="true" />
          <p class="mt-3 font-medium">Arrastra el Excel aquí</p>
          <p class="text-ink-muted">o <button type="button" class="text-primary hover:underline" @click="input?.click()">elige un archivo</button> · .xlsx o .xls, hasta {{ MAX_MB }} MB</p>
        </template>
        <input ref="input" type="file" accept=".xlsx,.xls" class="sr-only" tabindex="-1" aria-label="Excel de carga" @change="setFile(($event.target as HTMLInputElement).files?.[0])">
      </div>

      <div class="mt-5 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
        <div v-if="uploading" class="flex flex-1 items-center gap-3" role="status">
          <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-2">
            <div class="h-full rounded-full bg-primary transition-[width] duration-200" :style="{ width: `${Math.round((progress ?? 0) * 100)}%` }" />
          </div>
          <span class="shrink-0 text-[0.8125rem] text-ink-muted">
            {{ (progress ?? 0) < 1 ? `Subiendo ${Math.round((progress ?? 0) * 100)} %` : 'Leyendo el Excel…' }}
          </span>
        </div>
        <button type="button" class="btn btn-primary" :disabled="!file || uploading" @click="importar">
          <LoaderCircle v-if="uploading" class="size-4 animate-spin" aria-hidden="true" />
          Importar y abrir
        </button>
      </div>
    </section>
  </div>
</template>
