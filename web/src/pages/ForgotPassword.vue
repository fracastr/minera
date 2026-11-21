<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'
import { ref } from 'vue'
import AuthFrame from '@/components/AuthFrame.vue'
import Notice from '@/components/Notice.vue'
import TextField from '@/components/TextField.vue'
import { api, errorMessage, fieldError } from '@/lib/api'

const email = ref('')
const busy = ref(false)
const error = ref<unknown>(null)
const sent = ref('')

async function submit() {
  busy.value = true
  error.value = null
  try {
    const res = await api<{ message: string }>('/auth/forgot-password', { method: 'POST', body: { email: email.value.trim() } })
    sent.value = res.message
  } catch (e) {
    error.value = e
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AuthFrame title="Recuperar contraseña" description="Te enviaremos un enlace para crear una contraseña nueva.">
    <Notice v-if="sent" tone="ok">{{ sent }} El enlace vence en 60 minutos.</Notice>
    <form v-else class="space-y-5" novalidate @submit.prevent="submit">
      <Notice v-if="error && !fieldError(error, 'email')" tone="danger">{{ errorMessage(error) }}</Notice>
      <TextField v-model="email" label="Correo" type="email" autocomplete="email" required :error="fieldError(error, 'email')" />
      <button type="submit" class="btn btn-primary w-full" :disabled="busy || !email">
        {{ busy ? 'Enviando…' : 'Enviar enlace' }}
      </button>
    </form>
    <RouterLink to="/login" class="mt-6 inline-flex items-center gap-1.5 text-[0.8125rem] text-primary hover:underline">
      <ArrowLeft class="size-3.5" aria-hidden="true" /> Volver a ingresar
    </RouterLink>
  </AuthFrame>
</template>
