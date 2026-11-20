<script setup>
// Same two-column layout as pages/login.vue, shared by forgot/reset password.
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)
</script>

<template>
  <RouterLink to="/login">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

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
            {{ title }}
          </h4>
          <p
            v-if="subtitle"
            class="mb-0"
          >
            {{ subtitle }}
          </p>
        </VCardText>
        <VCardText>
          <slot />
          <RouterLink
            to="/login"
            class="d-flex align-center justify-center text-primary mt-6"
          >
            <VIcon
              icon="tabler-chevron-left"
              size="20"
              class="flip-in-rtl"
            />
            <span>Volver al login</span>
          </RouterLink>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
