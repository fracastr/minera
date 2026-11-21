<script lang="ts">
import type { Component } from 'vue'

export interface NavGroup {
  label?: string
  items: { to: string; label: string; icon: Component }[]
}
</script>

<script setup lang="ts">
import { TooltipContent, TooltipPortal, TooltipRoot, TooltipTrigger } from 'reka-ui'

defineProps<{ groups: NavGroup[]; collapsed: boolean }>()
defineEmits<{ navigate: [] }>()
</script>

<template>
  <nav class="px-2 py-3" aria-label="Navegación principal">
    <div v-for="(g, gi) in groups" :key="gi" :class="{ 'mt-5': gi > 0 }">
      <p v-if="g.label && !collapsed" class="px-3 pb-1.5 text-[0.75rem] font-medium text-rail-muted">{{ g.label }}</p>
      <div v-else-if="g.label" class="mx-3 mb-2 h-px bg-white/10" aria-hidden="true" />
      <ul class="space-y-0.5">
        <li v-for="item in g.items" :key="item.to">
          <TooltipRoot :disabled="!collapsed">
            <TooltipTrigger as-child>
              <RouterLink
                :to="{ name: item.to }"
                class="group flex h-9 items-center gap-3 rounded-md px-3 text-[0.8125rem] text-rail-ink/85 transition-colors hover:bg-rail-hover hover:text-rail-ink"
                exact-active-class="!bg-rail-active !text-white font-medium"
                :aria-label="collapsed ? item.label : undefined"
                @click="$emit('navigate')"
              >
                <component :is="item.icon" class="size-4.5 shrink-0 text-rail-muted group-hover:text-rail-ink group-aria-[current=page]:text-rail-accent" aria-hidden="true" />
                <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
              </RouterLink>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent side="right" :side-offset="8" class="z-50 rounded-md bg-navy px-2.5 py-1.5 text-xs text-white shadow-[0_4px_12px_rgb(0_0_0/0.2)]">
                {{ item.label }}
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </li>
      </ul>
    </div>
  </nav>
</template>
