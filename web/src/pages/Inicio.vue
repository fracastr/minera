<script setup lang="ts">
import { ArrowRight } from '@lucide/vue'
import { computed } from 'vue'
import UnderConstruction from '@/components/UnderConstruction.vue'
import { can } from '@/lib/abilities'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const firstName = computed(() => auth.user?.fullName.split(/\s+/)[0] ?? '')
const canCreate = computed(() => can(auth.user?.ability, 'create', 'Balances'))
</script>

<template>
  <div class="mx-auto max-w-6xl">
    <h1 class="font-display text-xl font-semibold">Hola, {{ firstName }}</h1>
    <p class="mt-1 text-ink-muted">Este será tu centro de control de balances.</p>
    <UnderConstruction
      summary="Balances recientes, mediciones fuera de tolerancia y medidores con error recurrente."
      class="mt-6"
    >
      <div class="mt-6 flex flex-wrap justify-center gap-2">
        <RouterLink v-if="canCreate" :to="{ name: 'balances-nuevo' }" class="btn btn-primary">Nuevo balance</RouterLink>
        <RouterLink :to="{ name: 'balances' }" class="btn btn-quiet">
          Ver listado <ArrowRight class="size-4" aria-hidden="true" />
        </RouterLink>
      </div>
    </UnderConstruction>
  </div>
</template>
