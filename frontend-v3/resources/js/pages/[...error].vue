<script setup>
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import miscMaskDark from '@images/pages/misc-mask-dark.png'
import miscMaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

const authThemeMask = useGenerateImageVariant(miscMaskLight, miscMaskDark)

definePage({
  alias: '/pages/misc/not-found/:error(.*)',
  meta: {
    layout: 'blank',
    public: true,
  },
})
</script>

<template>
  <div class="misc-wrapper">
    <div class="auth-logo d-flex align-center gap-x-3 mb-8">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="text-h4">
        {{ themeConfig.app.title }}
      </h1>
    </div>

    <ErrorHeader
      status-code="404"
      title="Página no encontrada"
      description="La dirección que buscas no existe o fue movida."
    />

    <VBtn
      to="/"
      class="mb-11"
    >
      Volver al inicio
    </VBtn>

    <img
      class="misc-footer-img d-none d-md-block"
      :src="authThemeMask"
      alt="misc-footer-img"
      height="320"
    >
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/misc.scss";
</style>
