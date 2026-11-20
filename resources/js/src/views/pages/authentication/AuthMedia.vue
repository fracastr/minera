<template>
  <div class="login-atrium__media">
    <img
      v-if="reducedMotion"
      src="/media/home/hero.webp"
      alt=""
      class="login-atrium__video"
    >
    <video
      v-else
      class="login-atrium__video"
      poster="/media/home/hero.webp"
      autoplay
      muted
      loop
      playsinline
      aria-hidden="true"
    >
      <source
        media="(max-width: 991px)"
        src="/media/home/hero-720.mp4"
        type="video/mp4"
      >
      <source
        src="/media/home/hero.mp4"
        type="video/mp4"
      >
    </video>
    <router-link
      :to="{ name: 'home' }"
      class="login-atrium__brand"
    >
      <span
        class="login-atrium__mark"
        aria-hidden="true"
      />
      <span>Sondek</span>
    </router-link>
    <p
      class="login-atrium__tagline"
      aria-hidden="true"
    >
      Balances metalúrgicos que cierran.
    </p>
  </div>
</template>

<script>
// Framed hero video shared by the login and password screens (styles in login-atrium.scss).
export default {
  data() {
    return {
      reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    }
  },
}
</script>
