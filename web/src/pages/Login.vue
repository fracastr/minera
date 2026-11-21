<script setup lang="ts">
import { FetchError } from 'ofetch'
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthFrame from '@/components/AuthFrame.vue'
import Notice from '@/components/Notice.vue'
import TextField from '@/components/TextField.vue'
import { errorMessage, fieldError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const busy = ref(false)
const error = ref<unknown>(null)
const message = ref('')

const expired = route.query.sesion === 'expirada'
const reset = route.query.restablecida === '1'

async function submit() {
  busy.value = true
  error.value = null
  message.value = ''
  try {
    await auth.login(email.value.trim(), password.value)
    const redirect = String(route.query.redirect ?? '')
    await router.replace(redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/inicio')
  } catch (e) {
    error.value = e
    if (e instanceof FetchError && e.status === 401) message.value = 'Correo o contraseña incorrectos.'
    else if (e instanceof FetchError && e.status === 422) message.value = ''
    else message.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AuthFrame title="Ingresar" description="Usa tu correo corporativo y tu contraseña.">
    <form class="space-y-5" novalidate @submit.prevent="submit">
      <Notice v-if="expired && !message" tone="info">Tu sesión se cerró por inactividad. Vuelve a ingresar para continuar.</Notice>
      <Notice v-if="reset && !message" tone="ok">Contraseña actualizada. Ya puedes ingresar con la nueva.</Notice>
      <Notice v-if="message" tone="danger">{{ message }}</Notice>

      <TextField v-model="email" label="Correo" type="email" autocomplete="username" required :error="fieldError(error, 'email')" />
      <div class="space-y-2">
        <TextField v-model="password" label="Contraseña" type="password" autocomplete="current-password" required :error="fieldError(error, 'password')" />
        <RouterLink to="/forgot-password" class="inline-block text-[0.8125rem] text-primary hover:underline">¿Olvidaste tu contraseña?</RouterLink>
      </div>

      <button type="submit" class="btn btn-primary w-full" :disabled="busy">
        {{ busy ? 'Ingresando…' : 'Ingresar' }}
      </button>
    </form>
  </AuthFrame>
</template>
