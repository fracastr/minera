<template>
  <div class="dashboard-home">
    <!--
      THESIS: Home after login is a plant control desk on the operation photo, not a SaaS marketing dashboard.
      OWN-WORLD: Atrium — dark panel, copper #e07a3a, mining still, quiet type. Same grammar as login.
      STORY: Operator knows this is Sondek, CMP is the site, and the next job is generate or review a balance.
      FIRST VIEWPORT: Full-bleed photo; centered wide desk with title and three task tiles spanning the scene.
      FORM: Login atrium extended into Operate. FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and DESIGN.md
    -->
    <div
      class="dashboard-home__scene"
      aria-hidden="true"
    >
      <b-img
        :src="hero.src"
        alt=""
        class="dashboard-home__photo"
      />
      <div class="dashboard-home__veil" />
    </div>

    <div class="dashboard-home__stage">
      <section
        class="dashboard-home__desk"
        aria-labelledby="dashboard-home-title"
      >
        <header class="dashboard-home__head">
          <b-img
            :src="appLogoImage"
            alt="Sondek"
            class="dashboard-home__logo"
          />
          <p class="dashboard-home__live">
            <span
              class="dashboard-home__live-dot"
              aria-hidden="true"
            />
            {{ $t('dashboard.status') }}
          </p>
        </header>

        <h1
          id="dashboard-home-title"
          class="dashboard-home__title"
        >
          {{ $t('dashboard.welcome') }}
        </h1>
        <p class="dashboard-home__lead">
          {{ $t('dashboard.subtitle') }}. {{ $t('dashboard.description') }}
        </p>

        <div
          class="dashboard-home__tiles"
          :class="'dashboard-home__tiles--' + tileCount"
        >
          <router-link
            v-if="canGenerate"
            :to="{ name: 'balances-BalanceFormWizard' }"
            class="dashboard-home__tile dashboard-home__tile--primary"
          >
            <feather-icon
              icon="UploadCloudIcon"
              size="22"
            />
            <span class="dashboard-home__tile-title">{{ $t('Generar Balance') }}</span>
            <span class="dashboard-home__tile-hint">{{ $t('dashboard.tiles.generate') }}</span>
          </router-link>

          <router-link
            v-if="canList"
            :to="{ name: 'balances-Listado' }"
            class="dashboard-home__tile"
          >
            <feather-icon
              icon="PieChartIcon"
              size="22"
            />
            <span class="dashboard-home__tile-title">{{ $t('Listado Balances') }}</span>
            <span class="dashboard-home__tile-hint">{{ $t('dashboard.tiles.list') }}</span>
          </router-link>

          <router-link
            v-if="canAnalytics"
            :to="{ name: 'dashboard-balance-analytics' }"
            class="dashboard-home__tile"
          >
            <feather-icon
              icon="BarChart2Icon"
              size="22"
            />
            <span class="dashboard-home__tile-title">{{ $t('Dashboard Analytics') }}</span>
            <span class="dashboard-home__tile-hint">{{ $t('dashboard.tiles.analytics') }}</span>
          </router-link>
        </div>
      </section>
    </div>

    <p class="dashboard-home__credit">
      {{ hero.credit }}
    </p>
  </div>
</template>

<script>
import { BImg } from 'bootstrap-vue'
import { $themeConfig } from '@themeConfig'
import { pickMiningHero } from '@/assets/images/pages/cmp/miningHeroes'

export default {
  name: 'Dashboard',
  components: {
    BImg,
  },
  setup() {
    const { appLogoImage } = $themeConfig.app
    return {
      appLogoImage,
      hero: pickMiningHero(),
    }
  },
  computed: {
    canGenerate() {
      return this.$can('create', 'Balances')
    },
    canList() {
      return this.$can('read', 'Balances')
    },
    canAnalytics() {
      return this.$can('read', 'DashboardAnalytics')
    },
    tileCount() {
      return [this.canGenerate, this.canList, this.canAnalytics].filter(Boolean).length
    },
  },
}
</script>

<style lang="scss">
@import './dashboard-home.scss';
</style>
