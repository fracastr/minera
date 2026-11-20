<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'Balances',
  },
})

const router = useRouter()

const listado = ref([])
const loading = ref(false)
const search = ref('')
const itemsPerPage = ref(10)

// Parse as local dates: new Date('YYYY-MM-DD') is UTC and shifts a day back in Chile.
const fmtDay = iso => {
  const [y, m, d] = iso.split('-').map(Number)

  return new Date(y, m - 1, d).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' })
}

const formatDate = value => {
  if (!value)
    return 'N/A'

  return new Date(value).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// Flatten the computed columns so the table's built-in search covers them (same fields the old customSearchFn checked).
const rows = computed(() => listado.value.map(row => ({
  ...row,
  periodo: row.periodo_inicio && row.periodo_fin ? `${fmtDay(row.periodo_inicio)} – ${fmtDay(row.periodo_fin)}` : 'Sin período',
  proceso_nombre: row.proceso?.nombre || 'N/A',
  valle_nombre: row.proceso?.valle?.nombre || 'N/A',
  fecha_creacion: formatDate(row.created_at),
})))

const headers = [
  { title: 'N°', key: 'numero', sortable: false, width: '70px', align: 'center' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Tipo', key: 'tipo' },
  { title: 'Período', key: 'periodo', sortRaw: (a, b) => String(a.periodo_inicio || '').localeCompare(String(b.periodo_inicio || '')) },
  { title: 'Proceso', key: 'proceso_nombre' },
  { title: 'Valle', key: 'valle_nombre' },
  { title: 'Usuario', key: 'usuario_nombre' },
  { title: 'Fecha Creación', key: 'fecha_creacion', sortRaw: (a, b) => String(a.created_at || '').localeCompare(String(b.created_at || '')) },
  { title: 'Acciones', key: 'acciones', sortable: false, width: '110px', align: 'center' },
]

const abrirBalance = row => router.push({ path: '/balances/BalanceFormWizard', query: { balance_id: row.id } })

const loadData = async () => {
  loading.value = true
  try {
    const res = await $api('/balances/get_listado')

    listado.value = res?.listado || []
    if (!res?.listado)
      console.warn('No se encontraron datos en la respuesta')
  }
  catch (e) {
    console.error('Error al cargar los datos:', e)
    listado.value = []
  }
  finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <VCard title="Listado de balances">
    <VCardText>
      <AppTextField
        v-model="search"
        placeholder="Buscar..."
        prepend-inner-icon="tabler-search"
        clearable
        style="max-inline-size: 320px;"
      />
    </VCardText>
    <VDataTable
      v-model:items-per-page="itemsPerPage"
      :headers="headers"
      :items="rows"
      :search="search"
      :loading="loading"
      :items-per-page-options="[5, 10, 20, 50].map(n => ({ value: n, title: String(n) }))"
      class="text-no-wrap"
      striped="even"
    >
      <template #item.acciones="{ item }">
        <VBtn
          size="small"
          variant="outlined"
          @click="abrirBalance(item)"
        >
          Abrir
        </VBtn>
      </template>
    </VDataTable>
  </VCard>
</template>
