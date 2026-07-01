<template>
  <b-img
    :src="appLogoImage"
    alt="CMP"
    class="app-brand-logo"
  />
</template>

<script>
import { BImg } from 'bootstrap-vue'
import { $themeConfig } from '@themeConfig'

export default {
  components: {
    BImg,
  },
  setup() {
    return {
      appLogoImage: $themeConfig.app.appLogoImage,
    }
  },
}
</script>

<style scoped>
.app-brand-logo {
  height: 28px;
  width: auto;
  max-width: 140px;
  object-fit: contain;
}
</style>
