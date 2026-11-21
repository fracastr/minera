<script setup lang="ts">
import { ArrowRight, ScanSearch, Scale, Workflow } from '@lucide/vue'
import FlowSketch from '@/components/FlowSketch.vue'

const points = [
  {
    icon: Scale,
    title: 'Reconciliación con solver',
    text: 'Ajusta toneladas y leyes de cada corriente para que todos los nodos cierren dentro de sus tolerancias.',
  },
  {
    icon: ScanSearch,
    title: 'Diagnóstico de mediciones',
    text: 'Señala las mediciones que no calzan con el balance y ordena qué revisar primero.',
  },
  {
    icon: Workflow,
    title: 'Diagrama interactivo',
    text: 'Recorre el proceso como diagrama de flujo, con valores medidos, reconciliados y su error.',
  },
]
const valleys = [
  { name: 'Copiapó', dot: 'bg-valle-copiapo' },
  { name: 'Huasco', dot: 'bg-valle-huasco' },
  { name: 'Elqui', dot: 'bg-valle-elqui' },
]
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-surface">
    <header class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 sm:px-6">
      <img src="/brand/cmp-logo.svg" alt="CMP, Compañía Minera del Pacífico" class="h-8 w-auto dark:hidden">
      <img src="/brand/cmp-logo-white.svg" alt="CMP, Compañía Minera del Pacífico" class="hidden h-8 w-auto dark:block">
      <RouterLink to="/login" class="btn btn-quiet h-9">Ingresar</RouterLink>
    </header>

    <main class="flex-1">
      <section class="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-12 pb-16 sm:px-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:pt-20 lg:pb-24">
        <div>
          <h1 class="font-display text-[clamp(2.5rem,6vw,4.25rem)] leading-[1.02] font-semibold">Balances CMP</h1>
          <p class="mt-5 max-w-[34ch] text-lg leading-relaxed text-ink-muted">
            Balances metalúrgicos reconciliados para las plantas de Compañía Minera del Pacífico.
          </p>
          <RouterLink to="/login" class="btn btn-primary mt-8 h-11 px-5 text-[0.9375rem]">
            Ingresar <ArrowRight class="size-4" aria-hidden="true" />
          </RouterLink>
        </div>
        <figure class="rounded-lg border border-line bg-canvas p-4 sm:p-6">
          <FlowSketch tone="canvas" />
          <figcaption class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line pt-3 text-xs text-ink-muted">
            <span><b class="font-medium text-ink">M</b> medido</span>
            <span><b class="font-medium text-ink">R</b> reconciliado</span>
            <span><b class="font-medium text-warn">E</b> error fuera de tolerancia</span>
          </figcaption>
        </figure>
      </section>

      <section aria-label="Qué hace" class="border-t border-line bg-canvas">
        <ul class="mx-auto grid max-w-6xl px-4 sm:px-6 md:grid-cols-3">
          <li
            v-for="p in points"
            :key="p.title"
            class="border-line py-8 not-last:border-b md:px-8 md:py-12 md:not-last:border-r md:not-last:border-b-0 md:first:pl-0 md:last:pr-0"
          >
            <component :is="p.icon" class="size-5 text-primary" stroke-width="1.75" aria-hidden="true" />
            <h2 class="mt-4 text-base font-semibold">{{ p.title }}</h2>
            <p class="mt-2 max-w-[42ch] leading-relaxed text-ink-muted">{{ p.text }}</p>
          </li>
        </ul>
      </section>
    </main>

    <footer class="border-t border-line">
      <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-xs text-ink-muted sm:px-6">
        <span>Compañía Minera del Pacífico</span>
        <span class="flex items-center gap-4">
          <span v-for="v in valleys" :key="v.name" class="flex items-center gap-1.5">
            <span class="size-2 rounded-full" :class="v.dot" aria-hidden="true" />Valle de {{ v.name }}
          </span>
        </span>
      </div>
    </footer>
  </div>
</template>
