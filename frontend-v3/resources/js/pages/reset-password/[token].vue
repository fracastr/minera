<script setup>
import AuthLayout from '@/views/pages/authentication/AuthLayout.vue'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const route = useRoute()
const router = useRouter()
const { success, error } = useNotify()

// Reset emails link to /reset-password/{token}?email=...; keep both sources like the old page.
const token = route.params.token || route.query.token
const email = ref(String(route.query.email || ''))
const password = ref('')
const passwordConfirmation = ref('')
const isPasswordVisible = ref(false)
const isLoading = ref(false)
const isValid = ref(false)
const formRef = ref()

const confirmRule = v => v === password.value || 'Las contraseñas no coinciden.'

onMounted(() => {
  if (!token) {
    error('Token de restablecimiento inválido o faltante.', { title: 'Error' })
    router.push('/login')
  }
})

async function resetPassword() {
  if (!(await formRef.value.validate()).valid) return
  isLoading.value = true
  try {
    await $api('/auth/reset-password', {
      method: 'POST',
      body: {
        email: email.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
        token,
      },
    })
    success('Tu contraseña ha sido restablecida exitosamente. Puedes iniciar sesión con tu nueva contraseña.', { title: 'Contraseña restablecida' })
    setTimeout(() => router.push('/login'), 2000)
  }
  catch (e) {
    error(e?.data?.message || 'Ocurrió un error al restablecer la contraseña.', { title: 'Error' })
  }
  finally {
    isLoading.value = false
  }
}
</script>

<template>
  <AuthLayout
    title="Nueva contraseña"
    subtitle="Ingresa tu nueva contraseña para restablecer tu cuenta"
  >
    <VForm
      ref="formRef"
      v-model="isValid"
      @submit.prevent="resetPassword"
    >
      <VRow>
        <VCol cols="12">
          <AppTextField
            v-model="email"
            label="Correo"
            type="email"
            readonly
            :rules="[requiredRule, emailRule]"
          />
        </VCol>
        <VCol cols="12">
          <AppTextField
            v-model="password"
            autofocus
            label="Nueva contraseña"
            placeholder="Nueva contraseña"
            autocomplete="new-password"
            :type="isPasswordVisible ? 'text' : 'password'"
            :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
            :rules="[requiredRule, strongPasswordRule]"
            hint="Mínimo 12 caracteres con mayúsculas, minúsculas, números y un carácter especial."
            persistent-hint
            @click:append-inner="isPasswordVisible = !isPasswordVisible"
          />
        </VCol>
        <VCol cols="12">
          <AppTextField
            v-model="passwordConfirmation"
            label="Confirmar contraseña"
            placeholder="Confirmar contraseña"
            autocomplete="new-password"
            :type="isPasswordVisible ? 'text' : 'password'"
            :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
            :rules="[requiredRule, confirmRule]"
            @click:append-inner="isPasswordVisible = !isPasswordVisible"
          />
        </VCol>
        <VCol cols="12">
          <VBtn
            block
            type="submit"
            :loading="isLoading"
            :disabled="isValid === false"
          >
            Restablecer contraseña
          </VBtn>
        </VCol>
      </VRow>
    </VForm>
  </AuthLayout>
</template>
