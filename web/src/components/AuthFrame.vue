<script setup lang="ts">
import FlowSketch from './FlowSketch.vue'

defineProps<{ title: string; description?: string }>()
</script>

<template>
  <div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
    <aside class="relative hidden flex-col justify-between overflow-hidden bg-rail p-10 text-rail-ink lg:flex">
      <RouterLink to="/" class="w-fit rounded">
        <img src="/brand/cmp-logo-white.svg" alt="CMP, Compañía Minera del Pacífico" class="h-9 w-auto">
      </RouterLink>
      <div class="max-w-md">
        <p class="font-display text-[1.75rem] leading-tight font-semibold">Balances CMP</p>
        <p class="mt-3 text-[0.9375rem] leading-relaxed text-rail-muted">
          Balances metalúrgicos reconciliados para las plantas de los valles de Copiapó, Huasco y Elqui.
        </p>
        <FlowSketch tone="rail" class="mt-10 -ml-2" />
      </div>
      <p class="text-xs text-rail-muted">Compañía Minera del Pacífico</p>
    </aside>

    <main id="contenido" tabindex="-1" class="flex flex-col px-4 py-8 outline-none sm:px-10">
      <RouterLink to="/" class="w-fit rounded lg:hidden">
        <img src="/brand/cmp-logo.svg" alt="CMP, Compañía Minera del Pacífico" class="h-8 w-auto dark:hidden">
        <img src="/brand/cmp-logo-white.svg" alt="CMP, Compañía Minera del Pacífico" class="hidden h-8 w-auto dark:block">
      </RouterLink>
      <div class="mx-auto w-full max-w-sm pt-12 pb-10 sm:my-auto sm:py-10">
        <h1 class="font-display text-2xl font-semibold">{{ title }}</h1>
        <p v-if="description" class="mt-2 text-ink-muted">{{ description }}</p>
        <div class="mt-8">
          <slot />
        </div>
      </div>
    </main>
  </div>
</template>
