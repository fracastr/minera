<script setup lang="ts">
import { Check, Circle } from '@lucide/vue'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthFrame from '@/components/AuthFrame.vue'
import Notice from '@/components/Notice.vue'
import TextField from '@/components/TextField.vue'
import { api, errorMessage, fieldError } from '@/lib/api'
import { passwordRules } from '@/lib/passwordRules'

const route = useRoute()
const router = useRouter()

const email = ref(String(route.query.email ?? ''))
const password = ref('')
const confirmation = ref('')
const busy = ref(false)
const error = ref<unknown>(null)

const checks = computed(() => passwordRules.map(r => ({ label: r.label, ok: r.test(password.value) })))
// Only complain once the confirmation stops being a prefix of the password (not while still typing it).
const mismatch = computed(() => confirmation.value !== '' && !password.value.startsWith(confirmation.value))
const ready = computed(() => email.value && checks.value.every(c => c.ok) && confirmation.value === password.value)

async function submit() {
  busy.value = true
  error.value = null
  try {
    await api('/auth/reset-password', {
      method: 'POST',
      body: { token: route.params.token, email: email.value.trim(), password: password.value, password_confirmation: confirmation.value },
    })
    await router.replace({ name: 'login', query: { restablecida: '1' } })
  } catch (e) {
    error.value = e
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AuthFrame title="Nueva contraseña" description="Elige una contraseña que no uses en otros sitios.">
    <form class="space-y-5" novalidate @submit.prevent="submit">
      <Notice v-if="error && !fieldError(error, 'password') && !fieldError(error, 'email')" tone="danger">
        {{ errorMessage(error) }}
        <RouterLink to="/forgot-password" class="font-medium text-primary hover:underline">Pedir un enlace nuevo</RouterLink>
      </Notice>

      <TextField v-model="email" label="Correo" type="email" autocomplete="username" required :readonly="!!route.query.email" :error="fieldError(error, 'email')" />
      <div class="space-y-3">
        <TextField v-model="password" label="Contraseña nueva" type="password" autocomplete="new-password" required :error="fieldError(error, 'password')" />
        <ul class="grid grid-cols-1 gap-x-4 gap-y-1 text-[0.8125rem] sm:grid-cols-2" aria-label="Requisitos de la contraseña">
          <li v-for="c in checks" :key="c.label" class="flex items-center gap-1.5" :class="c.ok ? 'text-ok' : 'text-ink-muted'">
            <component :is="c.ok ? Check : Circle" class="size-3.5 shrink-0" :class="{ 'opacity-60': !c.ok }" aria-hidden="true" />
            {{ c.label }}<span class="sr-only">{{ c.ok ? ': cumple' : ': pendiente' }}</span>
          </li>
        </ul>
      </div>
      <TextField
        v-model="confirmation" label="Repite la contraseña" type="password" autocomplete="new-password" required
        :error="mismatch ? 'Las contraseñas no coinciden.' : undefined"
      />

      <button type="submit" class="btn btn-primary w-full" :disabled="busy || !ready">
        {{ busy ? 'Guardando…' : 'Guardar contraseña' }}
      </button>
    </form>
  </AuthFrame>
</template>
