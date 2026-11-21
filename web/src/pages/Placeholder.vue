<script setup lang="ts">
import { useRoute } from 'vue-router'
import UnderConstruction from '@/components/UnderConstruction.vue'

const route = useRoute()
</script>

<template>
  <div class="mx-auto max-w-6xl">
    <h1 class="font-display text-xl font-semibold">{{ route.meta.title }}</h1>
    <UnderConstruction :key="route.fullPath" :summary="route.meta.summary" class="mt-6" />
  </div>
</template>
