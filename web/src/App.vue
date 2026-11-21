<script setup lang="ts">
import { announcement } from './router'
</script>

<template>
  <RouterView />
  <p class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</p>
</template>
