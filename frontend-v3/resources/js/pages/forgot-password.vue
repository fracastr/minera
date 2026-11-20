<script setup>
import AuthLayout from '@/views/pages/authentication/AuthLayout.vue'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const { success, error } = useNotify()
const formRef = ref()
const isValid = ref(false)
const email = ref('')
const isLoading = ref(false)

async function sendResetLink() {
  if (!(await formRef.value.validate()).valid) return
  isLoading.value = true
  try {
    await $api('/auth/forgot-password', { method: 'POST', body: { email: email.value } })
    success('Revisa tu correo para restablecer la contraseña.', { title: 'Enlace enviado' })
    email.value = ''
    formRef.value.resetValidation()
  }
  catch (e) {
    error(e?.data?.message || 'No se pudo enviar el enlace. Intenta de nuevo.', { title: 'Error' })
  }
  finally {
    isLoading.value = false
  }
}
</script>

<template>
  <AuthLayout
    title="Recuperar acceso"
    subtitle="Escribe tu correo y te enviaremos un enlace para restablecer la contraseña."
  >
    <VForm
      ref="formRef"
      v-model="isValid"
      @submit.prevent="sendResetLink"
    >
      <VRow>
        <VCol cols="12">
          <AppTextField
            v-model="email"
            autofocus
            label="Correo"
            type="email"
            autocomplete="username"
            placeholder="nombre@empresa.cl"
            :rules="[requiredRule, emailRule]"
          />
        </VCol>
        <VCol cols="12">
          <VBtn
            block
            type="submit"
            :loading="isLoading"
            :disabled="!email || isValid === false"
          >
            Enviar enlace
          </VBtn>
        </VCol>
      </VRow>
    </VForm>
  </AuthLayout>
</template>
