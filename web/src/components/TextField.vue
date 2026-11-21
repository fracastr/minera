<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue'
import { computed, ref, useId } from 'vue'

const props = defineProps<{
  label: string
  type?: string
  autocomplete?: string
  error?: string
  hint?: string
  required?: boolean
  readonly?: boolean
}>()
const model = defineModel<string>({ required: true })

const id = useId()
const reveal = ref(false)
const isPassword = computed(() => props.type === 'password')
const inputType = computed(() => (isPassword.value && reveal.value ? 'text' : props.type ?? 'text'))
const describedBy = computed(() => (props.error ? `${id}-error` : props.hint ? `${id}-hint` : undefined))
</script>

<template>
  <div class="space-y-1.5">
    <label :for="id" class="block text-[0.8125rem] font-medium text-ink">{{ label }}</label>
    <div class="relative">
      <input
        :id="id"
        v-model="model"
        :type="inputType"
        :autocomplete="autocomplete"
        :required="required"
        :readonly="readonly"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="describedBy"
        class="field read-only:bg-surface-2 read-only:text-ink-muted"
        :class="{ 'pr-10': isPassword }"
      >
      <button
        v-if="isPassword"
        type="button"
        class="absolute inset-y-0 right-0 grid w-10 place-items-center text-ink-faint hover:text-ink"
        :aria-label="reveal ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        :aria-pressed="reveal"
        @click="reveal = !reveal"
      >
        <component :is="reveal ? EyeOff : Eye" class="size-4" aria-hidden="true" />
      </button>
    </div>
    <p v-if="error" :id="`${id}-error`" class="text-[0.8125rem] text-danger">{{ error }}</p>
    <p v-else-if="hint" :id="`${id}-hint`" class="text-[0.8125rem] text-ink-muted">{{ hint }}</p>
  </div>
</template>
