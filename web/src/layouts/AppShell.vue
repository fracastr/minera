<script setup lang="ts">
import { ChartColumn, FilePlus, House, Menu, PanelLeftClose, PanelLeftOpen, Rows3, Users, Workflow, X } from '@lucide/vue'
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, TooltipProvider } from 'reka-ui'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SideNav, { type NavGroup } from '@/components/SideNav.vue'
import UserMenu from '@/components/UserMenu.vue'
import { can } from '@/lib/abilities'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const allGroups: NavGroup[] = [
  { items: [{ to: 'inicio', label: 'Inicio', icon: House }] },
  {
    label: 'Balances',
    items: [
      { to: 'balances', label: 'Listado', icon: Rows3 },
      { to: 'balances-nuevo', label: 'Nuevo balance', icon: FilePlus },
    ],
  },
  { items: [{ to: 'analytics', label: 'Analytics', icon: ChartColumn }] },
  {
    label: 'Administración',
    items: [
      { to: 'admin-usuarios', label: 'Usuarios', icon: Users },
      { to: 'admin-diagramas', label: 'Diagramas de proceso', icon: Workflow },
    ],
  },
]

// Only what the user's role can open: the same rule the router guard applies.
const groups = computed(() =>
  allGroups
    .map(g => ({
      ...g,
      items: g.items.filter(i => {
        const need = router.resolve({ name: i.to }).meta.can
        return !need || can(auth.user?.ability, ...need)
      }),
    }))
    .filter(g => g.items.length),
)

function readCollapsed() {
  try {
    return localStorage.getItem('cmp.sidebar') === 'collapsed'
  } catch {
    return false
  }
}
const collapsed = ref(readCollapsed())
watch(collapsed, v => {
  try {
    localStorage.setItem('cmp.sidebar', v ? 'collapsed' : 'expanded')
  } catch {}
})

const drawerOpen = ref(false)
watch(() => route.fullPath, () => (drawerOpen.value = false))
// The drawer only exists below lg: close it if the window grows past that.
const desktop = window.matchMedia('(min-width: 64rem)')
const onDesktop = (e: MediaQueryListEvent) => e.matches && (drawerOpen.value = false)
desktop.addEventListener('change', onDesktop)
onBeforeUnmount(() => desktop.removeEventListener('change', onDesktop))
</script>

<template>
  <TooltipProvider :delay-duration="300">
    <div class="flex min-h-dvh">
      <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-surface focus:px-3 focus:py-2">Saltar al contenido</a>

      <!-- Desktop rail -->
      <aside
        class="sticky top-0 hidden h-dvh shrink-0 flex-col bg-rail [--focus:var(--rail-accent)] text-rail-ink transition-[width] duration-200 ease-[var(--ease-out-expo)] lg:flex"
        :class="collapsed ? 'w-16' : 'w-60'"
      >
        <div class="flex h-14 items-center gap-2.5 px-4">
          <img src="/brand/cmp-logo-white.svg" alt="CMP" class="h-6 w-auto shrink-0" :class="{ 'h-5': collapsed }">
          <span v-if="!collapsed" class="font-display text-[0.9375rem] font-semibold">Balances</span>
        </div>
        <SideNav id="nav-rail" :groups="groups" :collapsed="collapsed" class="flex-1 overflow-y-auto" />
        <div class="border-t border-white/8 p-2">
          <button
            type="button"
            class="flex h-9 w-full items-center gap-3 rounded-md px-3 text-rail-muted hover:bg-rail-hover hover:text-rail-ink"
            aria-label="Barra lateral"
            aria-controls="nav-rail"
            :aria-expanded="!collapsed"
            @click="collapsed = !collapsed"
          >
            <component :is="collapsed ? PanelLeftOpen : PanelLeftClose" class="size-4.5 shrink-0" aria-hidden="true" />
            <span v-if="!collapsed" class="text-[0.8125rem]" aria-hidden="true">Contraer</span>
          </button>
        </div>
      </aside>

      <!-- Mobile drawer -->
      <DialogRoot v-model:open="drawerOpen">
        <DialogPortal>
          <DialogOverlay class="drawer-overlay fixed inset-0 z-40 bg-navy/50 lg:hidden" />
          <DialogContent class="drawer fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col bg-rail [--focus:var(--rail-accent)] text-rail-ink shadow-[4px_0_24px_rgb(0_0_0/0.25)] lg:hidden">
            <div class="flex h-14 items-center justify-between px-4">
              <DialogTitle class="flex items-center gap-2.5">
                <img src="/brand/cmp-logo-white.svg" alt="CMP" class="h-6 w-auto">
                <span class="font-display text-[0.9375rem] font-semibold">Balances</span>
              </DialogTitle>
              <DialogDescription class="sr-only">Navegación principal</DialogDescription>
              <button type="button" class="grid size-9 place-items-center rounded-md text-rail-muted hover:bg-rail-hover hover:text-rail-ink" aria-label="Cerrar menú" @click="drawerOpen = false">
                <X class="size-5" aria-hidden="true" />
              </button>
            </div>
            <SideNav :groups="groups" :collapsed="false" class="flex-1 overflow-y-auto" @navigate="drawerOpen = false" />
          </DialogContent>
        </DialogPortal>
      </DialogRoot>

      <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-line bg-surface px-4 sm:px-6">
          <button type="button" class="-ml-1.5 grid size-9 place-items-center rounded-md text-ink-muted hover:bg-surface-2 hover:text-ink lg:hidden" aria-label="Abrir menú" @click="drawerOpen = true">
            <Menu class="size-5" aria-hidden="true" />
          </button>
          <nav aria-label="Ruta" class="min-w-0 flex-1">
            <ol class="flex items-center gap-1.5 truncate text-[0.8125rem]">
              <li v-if="route.meta.section" class="text-ink-muted">{{ route.meta.section }}</li>
              <li v-if="route.meta.section" class="text-ink-faint" aria-hidden="true">/</li>
              <li class="truncate font-medium" aria-current="page">{{ route.meta.title }}</li>
            </ol>
          </nav>
          <UserMenu />
        </header>

        <main id="contenido" tabindex="-1" class="flex-1 px-4 py-6 outline-none sm:px-6 lg:px-8 lg:py-8">
          <RouterView />
        </main>
      </div>
    </div>
  </TooltipProvider>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
  .drawer[data-state='open'] {
    animation: drawer-in 260ms var(--ease-out-expo);
  }
  .drawer[data-state='closed'] {
    animation: drawer-in 180ms ease-in reverse;
  }
  .drawer-overlay[data-state='open'] {
    animation: fade 200ms ease-out;
  }
  .drawer-overlay[data-state='closed'] {
    animation: fade 160ms ease-in reverse;
  }
}
@keyframes drawer-in {
  from {
    transform: translateX(-100%);
  }
}
@keyframes fade {
  from {
    opacity: 0;
  }
}
</style>
