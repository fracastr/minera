<script setup>
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const form = ref({
  email: '',
  password: '',
  remember: false,
})

const isPasswordVisible = ref(false)
const errorMessage = ref('')
const isLoading = ref(false)
const route = useRoute()
const router = useRouter()
const ability = useAbility()

// Same endpoint as the current app: POST /api/auth/login → { accessToken, userData: { ability, ... } }.
async function login() {
  errorMessage.value = ''
  isLoading.value = true
  try {
    const res = await $api('/auth/login', {
      method: 'POST',
      body: { email: form.value.email, password: form.value.password, remember_me: form.value.remember },
    })
    useCookie('userAbilityRules').value = res.userData.ability
    ability.update(res.userData.ability)
    useCookie('userData').value = res.userData
    useCookie('accessToken').value = res.accessToken
    await router.replace(route.query.to ? String(route.query.to) : '/')
  }
  catch (e) {
    const status = e?.response?.status
    if (status === 401) errorMessage.value = 'Correo o contraseña incorrectos.'
    else if (status === 429) errorMessage.value = 'Demasiados intentos. Espera un momento y vuelve a intentarlo.'
    else errorMessage.value = e?.data?.message || 'No se pudo iniciar sesión. Intenta de nuevo.'
  }
  finally {
    isLoading.value = false
  }
}
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)
</script>

<template>
  <a href="javascript:void(0)">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </a>

  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 6.25rem;"
        >
          <div style="max-inline-size: 460px;">
            <h2 class="text-h3 mb-4">
              Balances metalúrgicos reconciliados
            </h2>
            <p class="text-body-1 mb-0">
              Carga las mediciones del período, corre el balance y revisa el ajuste de cada flujo y el cierre de cada nodo.
            </p>
          </div>
        </div>

        <img
          class="auth-footer-mask flip-in-rtl"
          :src="authThemeMask"
          alt="auth-footer-mask"
          height="280"
          width="100"
        >
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-6"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            Bienvenido a {{ themeConfig.app.title }}
          </h4>
          <p class="mb-0">
            Ingresa con tu cuenta para continuar
          </p>
        </VCardText>
        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorMessage }}
          </VAlert>
          <VForm @submit.prevent="login">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  autofocus
                  label="Correo"
                  type="email"
                  autocomplete="username"
                  placeholder="nombre@empresa.cl"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  label="Contraseña"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="current-password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="d-flex align-center flex-wrap justify-space-between my-6">
                  <VCheckbox
                    v-model="form.remember"
                    label="Recordarme"
                  />
                  <a
                    class="text-primary"
                    href="/forgot-password"
                  >
                    ¿Olvidaste tu contraseña?
                  </a>
                </div>

                <VBtn
                  block
                  type="submit"
                  :loading="isLoading"
                >
                  Ingresar
                </VBtn>
              </VCol>

            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
