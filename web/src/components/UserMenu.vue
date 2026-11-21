<script setup lang="ts">
import { ChevronsUpDown, LogOut, Monitor, Moon, Sun } from '@lucide/vue'
import {
  DropdownMenuContent, DropdownMenuItem, DropdownMenuItemIndicator, DropdownMenuLabel, DropdownMenuPortal,
  DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger,
} from 'reka-ui'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { themeMode } from '@/lib/theme'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const roleLabel = { admin: 'Administrador', operator: 'Operador', viewer: 'Lector' }
const role = computed(() => roleLabel[auth.user?.role ?? 'viewer'] ?? auth.user?.role)
const initials = computed(() =>
  (auth.user?.fullName ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]!.toUpperCase()).join(''),
)
const themes = [
  { value: 'system', label: 'Sistema', icon: Monitor },
  { value: 'light', label: 'Claro', icon: Sun },
  { value: 'dark', label: 'Oscuro', icon: Moon },
] as const

async function logout() {
  await auth.logout()
  await router.replace({ name: 'login' })
}

const item = 'flex h-8 cursor-default items-center gap-2.5 rounded px-2 text-[0.8125rem] outline-none select-none data-[highlighted]:bg-surface-2'
</script>

<template>
  <DropdownMenuRoot>
    <DropdownMenuTrigger class="-mr-1.5 flex items-center gap-2.5 rounded-md py-1 pr-1.5 pl-1 hover:bg-surface-2 data-[state=open]:bg-surface-2" aria-label="Menú de usuario">
      <span class="grid size-8 place-items-center rounded-full bg-navy text-xs font-semibold text-white dark:bg-rail-active" aria-hidden="true">{{ initials }}</span>
      <span class="hidden text-left leading-tight sm:block">
        <span class="block max-w-44 truncate text-[0.8125rem] font-medium">{{ auth.user?.fullName }}</span>
        <span class="block text-xs text-ink-muted">{{ role }}</span>
      </span>
      <ChevronsUpDown class="hidden size-3.5 text-ink-faint sm:block" aria-hidden="true" />
    </DropdownMenuTrigger>
    <DropdownMenuPortal>
      <DropdownMenuContent
        align="end" :side-offset="6"
        class="menu z-50 w-64 rounded-lg border border-line bg-surface p-1.5 text-ink shadow-[0_8px_24px_rgb(16_20_40/0.14)]"
      >
        <DropdownMenuLabel class="px-2 pt-1.5 pb-2">
          <span class="block truncate font-medium">{{ auth.user?.fullName }}</span>
          <span class="block truncate text-xs text-ink-muted">{{ auth.user?.email }}</span>
          <span class="mt-1.5 inline-block rounded bg-surface-2 px-1.5 py-0.5 text-[0.6875rem] font-medium text-ink-muted">{{ role }}</span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator class="my-1 h-px bg-line" />
        <DropdownMenuLabel class="px-2 pt-1 pb-1 text-xs text-ink-muted">Tema</DropdownMenuLabel>
        <DropdownMenuRadioGroup v-model="themeMode">
          <DropdownMenuRadioItem v-for="t in themes" :key="t.value" :value="t.value" :class="item">
            <component :is="t.icon" class="size-4 text-ink-muted" aria-hidden="true" />
            <span class="flex-1">{{ t.label }}</span>
            <DropdownMenuItemIndicator class="size-1.5 rounded-full bg-primary" />
          </DropdownMenuRadioItem>
        </DropdownMenuRadioGroup>
        <DropdownMenuSeparator class="my-1 h-px bg-line" />
        <DropdownMenuItem :class="item" @select="logout">
          <LogOut class="size-4 text-ink-muted" aria-hidden="true" />
          Cerrar sesión
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenuPortal>
  </DropdownMenuRoot>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
  .menu[data-state='open'] {
    animation: menu-in 160ms var(--ease-out-expo);
    transform-origin: var(--reka-dropdown-menu-content-transform-origin);
  }
}
@keyframes menu-in {
  from {
    opacity: 0;
    transform: scale(0.97);
  }
}
</style>
