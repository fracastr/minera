<script setup lang="ts">
import { valleColor } from '@/lib/format'

defineProps<{ proceso: string | null; valle: string | null }>()
</script>

<template>
  <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-line bg-surface px-2 py-0.5 text-xs whitespace-nowrap">
    <span class="size-2 shrink-0 rounded-full" :style="{ background: valleColor(valle) }" aria-hidden="true" />
    <span class="truncate font-medium">{{ proceso ?? 'Sin proceso' }}</span>
    <span v-if="valle" class="text-ink-faint">{{ valle }}</span>
  </span>
</template>
