<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { computed, reactive, ref, watch } from 'vue'
import TextField from '@/components/TextField.vue'
import { sugerirFin } from '@/domain/periodo'
import type { BalanceRef } from '@/domain/types'

const props = defineProps<{ balance: BalanceRef | null; busy: boolean }>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ guardar: [body: { nombre: string; tipo: 'quincenal' | 'mensual'; periodo_inicio: string; periodo_fin: string }] }>()

const form = reactive({ nombre: '', tipo: 'quincenal' as 'quincenal' | 'mensual', periodo_inicio: '', periodo_fin: '' })
const tried = ref(false)

// Prefilled from the balance being overwritten, as v1 did.
watch(open, v => {
  if (!v) return
  tried.value = false
  Object.assign(form, {
    nombre: props.balance?.nombre ?? '',
    tipo: props.balance?.tipo === 'mensual' ? 'mensual' : 'quincenal',
    periodo_inicio: props.balance?.periodo_inicio ?? '',
    periodo_fin: props.balance?.periodo_fin ?? '',
  })
})
// Suggest only when the user changes start or type, so an overwritten balance keeps its saved end.
function sugerir(inicio = form.periodo_inicio) {
  if (inicio) form.periodo_fin = sugerirFin(inicio, form.tipo)
}

const errors = computed(() => ({
  nombre: !form.nombre.trim() ? 'Ponle un nombre al balance.' : '',
  periodo_inicio: !form.periodo_inicio ? 'Indica el inicio del período.' : '',
  periodo_fin: !form.periodo_fin ? 'Indica el fin del período.' : form.periodo_fin < form.periodo_inicio ? 'El fin no puede ser anterior al inicio.' : '',
}))

function submit() {
  tried.value = true
  if (Object.values(errors.value).some(Boolean)) return
  emit('guardar', { ...form, nombre: form.nombre.trim() })
}
const err = (k: keyof typeof errors.value) => (tried.value ? errors.value[k] : '') || undefined
const radio = 'flex h-10 flex-1 cursor-pointer items-center justify-center rounded-md border text-[0.8125rem] font-medium has-[:checked]:border-primary has-[:checked]:bg-primary-soft has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-[var(--focus)]'
</script>

<template>
  <DialogRoot v-model:open="open">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-50 bg-navy/45" />
      <DialogContent class="fixed top-1/2 left-1/2 z-50 max-h-[90dvh] w-[min(30rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg border border-line bg-surface p-5 shadow-[0_16px_48px_rgb(16_20_40/0.22)]">
        <DialogTitle class="font-display text-base font-semibold">{{ balance ? 'Sobrescribir balance' : 'Guardar balance' }}</DialogTitle>
        <DialogDescription class="mt-1 text-ink-muted">
          {{ balance ? `Se actualizarán el nombre, el período y los datos de «${balance.nombre}».` : 'Queda en el listado con los datos y la última corrida de esta sesión.' }}
        </DialogDescription>
        <form class="mt-5 space-y-4" novalidate @submit.prevent="submit">
          <TextField v-model="form.nombre" label="Nombre" :error="err('nombre')" required />
          <fieldset>
            <legend class="mb-1.5 text-[0.8125rem] font-medium">Tipo</legend>
            <div class="flex gap-2">
              <label :class="radio"><input v-model="form.tipo" type="radio" value="quincenal" class="sr-only" @change="sugerir()"> Quincenal</label>
              <label :class="radio"><input v-model="form.tipo" type="radio" value="mensual" class="sr-only" @change="sugerir()"> Mensual</label>
            </div>
          </fieldset>
          <div class="grid gap-4 sm:grid-cols-2">
            <TextField v-model="form.periodo_inicio" type="date" label="Inicio del período" :error="err('periodo_inicio')" required @update:model-value="sugerir" />
            <TextField v-model="form.periodo_fin" type="date" label="Fin del período" :error="err('periodo_fin')" hint="Sugerido según el tipo" required />
          </div>
          <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
            <DialogClose class="btn btn-quiet" type="button">Cancelar</DialogClose>
            <button type="submit" class="btn btn-primary" :disabled="busy">
              <LoaderCircle v-if="busy" class="size-4 animate-spin" aria-hidden="true" />
              {{ balance ? 'Sobrescribir' : 'Guardar' }}
            </button>
          </div>
        </form>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
