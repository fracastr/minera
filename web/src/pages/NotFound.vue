<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <main class="grid min-h-dvh place-items-center px-4 text-center">
    <div>
      <p class="font-display text-5xl font-semibold text-ink-faint tabular-nums">404</p>
      <h1 class="mt-4 font-display text-xl font-semibold">Esta página no existe</h1>
      <p class="mt-2 text-ink-muted">Revisa la dirección o vuelve al inicio.</p>
      <RouterLink :to="auth.loggedIn ? '/inicio' : '/'" class="btn btn-primary mt-6">Ir al inicio</RouterLink>
    </div>
  </main>
</template>
