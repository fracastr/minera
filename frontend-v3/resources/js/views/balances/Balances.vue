<script setup>
// STUB — replaced by the real port of resources/js/src/views/balances/Balances.vue (Vue 2 app).
// The wizard depends on this contract; the real component must keep it.
const props = defineProps({
  proceso: { type: Number, default: null },
  show_tables: { type: Boolean, default: false },
  sourceBalanceId: { type: Number, default: null },
  editMode: { type: String, default: null },
})

defineEmits(['edit-mode-chosen'])

const datos_entrada = ref({})
const datos_entrada_id = ref(null)

// Fills the tables from a /api/balances/* response (axios-like { data } or the raw payload).
function applyBalancePayload(response) {
  const data = response?.data || response
  datos_entrada.value = data?.data?.datos_entrada || {}
  datos_entrada_id.value = data?.datos_entrada_id ?? null
}

// Asks whether to overwrite or fork the balance before modifying it; resolves true when it may proceed.
async function ensureEditDecision() {
  return true
}

defineExpose({ applyBalancePayload, ensureEditDecision, datos_entrada, datos_entrada_id })
</script>

<template>
  <VAlert type="info" variant="tonal">
    Tablas del balance: pendiente de migración.
  </VAlert>
</template>
