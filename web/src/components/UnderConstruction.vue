<script setup lang="ts">
import { DraftingCompass } from '@lucide/vue'

defineProps<{ summary?: string }>()
</script>

<template>
  <!-- Drawn like a drawing sheet with its title block: the page exists, its content is still on the board. -->
  <section class="relative grid min-h-80 place-items-center rounded-lg border border-dashed border-line-strong bg-surface px-6 py-14 text-center">
    <div class="max-w-md">
      <DraftingCompass class="mx-auto size-8 text-ink-faint" stroke-width="1.5" aria-hidden="true" />
      <h2 class="mt-4 font-display text-lg font-semibold">En construcción</h2>
      <p v-if="summary" class="mt-2 text-ink-muted">{{ summary }}</p>
      <p class="mt-1 text-ink-muted">Esta sección llega en una próxima entrega de la nueva versión.</p>
      <slot />
    </div>
    <dl class="absolute right-0 bottom-0 hidden grid-cols-[auto_auto] border-t border-l border-line text-left text-[0.6875rem] sm:grid">
      <dt class="border-r border-b border-line px-2 py-1 text-ink-faint">Estado</dt>
      <dd class="border-b border-line px-2 py-1 font-medium text-warn">Pendiente</dd>
      <dt class="border-r border-line px-2 py-1 text-ink-faint">Versión</dt>
      <dd class="px-2 py-1 font-medium text-ink-muted">v2</dd>
    </dl>
  </section>
</template>
