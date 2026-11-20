<script setup>
import Balance from '@/views/balances/Balances.vue'

definePage({
  meta: {
    action: 'create',
    subject: 'Balances',
  },
})

const { t } = useI18n()
const route = useRoute()
const { success, error } = useNotify()

const nombre_balance = ref('')
const tipo = ref('quincenal')
const periodo_inicio = ref('')
const periodo_fin = ref('')
const savedMeta = ref(null)
const valle = ref(null)
const proceso = ref(null)
const valles = ref([])
const procesos = ref([])
const loading = ref(false)
const balance_id = ref(null)
const sourceBalanceId = ref(null)
const editMode = ref(null)
const show_tables = ref(false)
const step = ref(0)
const saveDialog = ref(false)

const balancesRef = ref()
const formValle = ref()
const formProceso = ref()
const formSave = ref()

const steps = computed(() => [
  { title: t('balanceFormWizard.valleSelection.title'), icon: 'tabler-map-pin' },
  { title: t('balanceFormWizard.procesoSelection.title'), icon: 'tabler-settings' },
  { title: t('balanceFormWizard.balances.title'), icon: 'tabler-chart-bar' },
])

const requiredRule = name => v => (v !== null && v !== undefined && v !== '') || t('balanceFormWizard.required', { field: name })

const tipoOptions = computed(() => ['quincenal', 'mensual'].map(value => ({ value, title: t(`balanceFormWizard.saveModal.tipos.${value}`) })))

const periodoState = computed(() => {
  if (!periodo_inicio.value || !periodo_fin.value)
    return null

  return periodo_fin.value >= periodo_inicio.value ? null : false
})

const canSave = computed(() => !!nombre_balance.value.trim() && !!periodo_inicio.value && !!periodo_fin.value && periodoState.value !== false)

// Moving forward validates each step being left (the old wizard's before-change).
const stepForms = [formValle, formProceso]

const goTo = async target => {
  if (loading.value)
    return
  for (let i = step.value; i < target; i++) {
    const { valid } = await stepForms[i].value.validate()
    if (!valid) {
      step.value = i

      return
    }
  }
  step.value = target
}

const loadProcesos = async valleId => {
  const res = await $api(`/balances/getProcesos/${valleId}`)

  procesos.value = res.procesos
}

const reopenBalance = async balanceId => {
  const res = await $api(`/balances/load_balance/${balanceId}`)
  const { balance } = res

  sourceBalanceId.value = balance.id
  balance_id.value = balance.id
  savedMeta.value = balance
  nombre_balance.value = balance.nombre
  valle.value = balance.valle_id
  editMode.value = null

  await loadProcesos(valle.value)
  proceso.value = balance.proceso_id
  show_tables.value = true

  await nextTick()

  // Axios-shaped, as the old wizard passed it: the payload itself has a `data` key.
  balancesRef.value?.applyBalancePayload({ data: res })
  step.value = 2
}

onMounted(async () => {
  loading.value = true
  try {
    const res = await $api('/balances/getValles/1')

    valles.value = res.valles

    const reopenId = Number(route.query.balance_id)
    if (reopenId)
      await reopenBalance(reopenId)
  }
  catch (e) {
    console.log('FAILURE!!', e)
    error(t('balanceFormWizard.toast.loadError'))
  }
  finally {
    loading.value = false
  }
})

const onEditModeChosen = mode => {
  editMode.value = mode
  if (mode === 'new') {
    sourceBalanceId.value = null
    balance_id.value = null
  }
}

const vallesChange = async () => {
  show_tables.value = false
  proceso.value = null
  loading.value = true
  try {
    await loadProcesos(valle.value)
  }
  catch (e) {
    console.log('FAILURE!!', e)
    error(t('balanceFormWizard.toast.processError'))
  }
  finally {
    loading.value = false
  }
}

// Prefill from the reopened/last saved balance so overwriting keeps its name and period.
const resetModal = () => {
  const meta = savedMeta.value || {}

  nombre_balance.value = meta.nombre || ''
  tipo.value = meta.tipo === 'mensual' ? 'mensual' : 'quincenal'
  periodo_inicio.value = meta.periodo_inicio || ''
  periodo_fin.value = meta.periodo_fin || ''
  formSave.value?.resetValidation()
}

const openSaveDialog = () => {
  resetModal()
  saveDialog.value = true
}

const suggestPeriodoFin = () => {
  if (!periodo_inicio.value)
    return
  const [y, m, d] = periodo_inicio.value.split('-').map(Number)
  const lastDay = new Date(y, m, 0).getDate()
  const day = tipo.value === 'quincenal' && d <= 15 ? 15 : lastDay

  periodo_fin.value = `${y}-${String(m).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

const onTipoChange = value => {
  tipo.value = value
  suggestPeriodoFin()
}

const onInicioChange = value => {
  if (value === periodo_inicio.value)
    return
  periodo_inicio.value = value
  suggestPeriodoFin()
}

const handleSubmit = async () => {
  const { valid } = await formSave.value.validate()
  if (!valid || !canSave.value)
    return

  if (balancesRef.value) {
    const canModify = await balancesRef.value.ensureEditDecision()
    if (!canModify)
      return
  }

  const overwrite = editMode.value === 'overwrite' && !!sourceBalanceId.value
  try {
    const res = await $api('/balances/save_balance', {
      method: 'POST',
      body: {
        datos_entrada: unref(balancesRef.value.datos_entrada),
        nombre_balance: nombre_balance.value,
        tipo: tipo.value,
        periodo_inicio: periodo_inicio.value,
        periodo_fin: periodo_fin.value,
        datos_entrada_id: unref(balancesRef.value.datos_entrada_id),
        proceso_id: proceso.value,
        overwrite,
        balance_id: overwrite ? sourceBalanceId.value : null,
      },
    })

    balance_id.value = res.balance_id
    sourceBalanceId.value = res.balance_id
    savedMeta.value = {
      nombre: nombre_balance.value,
      tipo: tipo.value,
      periodo_inicio: periodo_inicio.value,
      periodo_fin: periodo_fin.value,
    }
    editMode.value = null
    success(t('balanceFormWizard.toast.success'))
  }
  catch (e) {
    console.log('FAILURE!! correr_balance', e)
    error(t('balanceFormWizard.toast.error'))
  }
  saveDialog.value = false
}
</script>

<template>
  <div>
    <!-- Header -->
    <VCard class="mb-6">
      <VCardText class="d-flex align-center justify-center gap-4 text-center flex-wrap">
        <VAvatar
          color="primary"
          variant="tonal"
          size="64"
        >
          <VIcon
            icon="tabler-trending-up"
            size="32"
          />
        </VAvatar>
        <div>
          <h4 class="text-h4 mb-1">
            {{ t('balanceFormWizard.header.title') }}
          </h4>
          <p class="text-body-1 mb-0">
            {{ t('balanceFormWizard.header.subtitle') }}
          </p>
        </div>
      </VCardText>
    </VCard>

    <!-- Loading overlay -->
    <VOverlay
      :model-value="loading"
      persistent
      class="align-center justify-center"
    >
      <VCard
        class="text-center pa-8"
        min-width="320"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="56"
          class="mb-6"
        />
        <h5 class="text-h5 mb-1">
          {{ t('balanceFormWizard.loading.title') }}
        </h5>
        <p class="text-body-1 mb-4">
          {{ t('balanceFormWizard.loading.subtitle') }}
        </p>
        <VProgressLinear
          indeterminate
          color="primary"
          rounded
          class="mb-2"
        />
        <div class="text-body-2 mb-4">
          {{ t('balanceFormWizard.loading.progress') }}
        </div>
        <div class="d-flex align-center justify-center text-body-2">
          <VIcon
            icon="tabler-database"
            size="16"
            color="primary"
            class="me-2"
          />
          {{ t('balanceFormWizard.loading.systemName') }}
        </div>
      </VCard>
    </VOverlay>

    <!-- Wizard -->
    <VCard>
      <VCardText>
        <AppStepper
          :current-step="step"
          :items="steps"
          align="center"
          @update:current-step="goTo"
        />
      </VCardText>
      <VDivider />
      <VCardText>
        <VWindow
          v-model="step"
          class="disable-tab-transition"
          :touch="false"
        >
          <VWindowItem eager>
            <VForm
              ref="formValle"
              @submit.prevent
            >
              <h5 class="text-h5 mb-1">
                {{ t('balanceFormWizard.valleSelection.systemTitle') }}
              </h5>
              <p class="text-body-1 mb-6">
                {{ t('balanceFormWizard.valleSelection.subtitle') }}
              </p>
              <AppSelect
                v-model="valle"
                :items="valles"
                item-title="text"
                item-value="value"
                :label="t('balanceFormWizard.valleSelection.label')"
                :placeholder="t('balanceFormWizard.valleSelection.placeholder')"
                :rules="[requiredRule(t('balanceFormWizard.valleSelection.name'))]"
                :disabled="loading"
                @update:model-value="vallesChange"
              />
            </VForm>
          </VWindowItem>

          <VWindowItem eager>
            <VForm
              ref="formProceso"
              @submit.prevent
            >
              <h5 class="text-h5 mb-1">
                {{ t('balanceFormWizard.procesoSelection.systemTitle') }}
              </h5>
              <p class="text-body-1 mb-6">
                {{ t('balanceFormWizard.procesoSelection.subtitle') }}
              </p>
              <AppSelect
                v-model="proceso"
                :items="procesos"
                item-title="text"
                item-value="value"
                :label="t('balanceFormWizard.procesoSelection.label')"
                :placeholder="t('balanceFormWizard.procesoSelection.placeholder')"
                :rules="[requiredRule(t('balanceFormWizard.procesoSelection.name'))]"
                :disabled="loading"
              />
            </VForm>
          </VWindowItem>

          <VWindowItem eager>
            <h5 class="text-h5 mb-1">
              {{ t('balanceFormWizard.balances.configTitle') }}
            </h5>
            <p class="text-body-1 mb-6">
              {{ t('balanceFormWizard.balances.configSubtitle') }}
            </p>
            <Balance
              ref="balancesRef"
              :proceso="proceso"
              :show_tables="show_tables"
              :source-balance-id="sourceBalanceId"
              :edit-mode="editMode"
              @edit-mode-chosen="onEditModeChosen"
            />
          </VWindowItem>
        </VWindow>
      </VCardText>
      <VDivider />
      <VCardText class="d-flex justify-space-between flex-wrap gap-4">
        <VBtn
          v-if="step > 0"
          color="secondary"
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          :disabled="loading"
          @click="step--"
        >
          {{ t('balanceFormWizard.backButton') }}
        </VBtn>
        <VSpacer />
        <VBtn
          v-if="step < steps.length - 1"
          append-icon="tabler-arrow-right"
          :disabled="loading"
          @click="goTo(step + 1)"
        >
          {{ t('balanceFormWizard.nextButton') }}
        </VBtn>
        <VBtn
          v-else
          prepend-icon="tabler-device-floppy"
          :disabled="loading"
          @click="openSaveDialog"
        >
          {{ t('balanceFormWizard.finishButton') }}
        </VBtn>
      </VCardText>
    </VCard>

    <!-- Save balance dialog -->
    <VDialog
      v-model="saveDialog"
      max-width="800"
    >
      <DialogCloseBtn @click="saveDialog = false" />
      <VCard :title="t('balanceFormWizard.saveModal.title')">
        <VCardText>
          <p class="text-body-1 mb-6">
            {{ t('balanceFormWizard.saveModal.subtitle') }}
          </p>
          <VForm
            ref="formSave"
            @submit.prevent="handleSubmit"
          >
            <VRow>
              <VCol cols="12">
                <AppTextField
                  id="nombre_balance"
                  v-model="nombre_balance"
                  :label="t('balanceFormWizard.saveModal.label')"
                  :placeholder="t('balanceFormWizard.saveModal.placeholder')"
                  :rules="[v => !!v?.trim() || t('balanceFormWizard.saveModal.invalidFeedback')]"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppSelect
                  :model-value="tipo"
                  :items="tipoOptions"
                  :label="t('balanceFormWizard.saveModal.tipo')"
                  @update:model-value="onTipoChange"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  :model-value="periodo_inicio"
                  :label="t('balanceFormWizard.saveModal.periodoInicio')"
                  @update:model-value="onInicioChange"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <!-- AppDateTimePicker reads `config` once; the key remounts it so minDate follows periodo_inicio. -->
                <AppDateTimePicker
                  :key="periodo_inicio"
                  v-model="periodo_fin"
                  :label="t('balanceFormWizard.saveModal.periodoFin')"
                  :config="{ minDate: periodo_inicio || null }"
                  :error-messages="periodoState === false ? t('balanceFormWizard.saveModal.periodoInvalid') : []"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardText class="d-flex justify-space-between flex-wrap gap-4">
          <VBtn
            color="secondary"
            variant="tonal"
            prepend-icon="tabler-x"
            @click="saveDialog = false"
          >
            {{ t('balanceFormWizard.saveModal.cancel') }}
          </VBtn>
          <VBtn
            prepend-icon="tabler-device-floppy"
            :disabled="!canSave"
            @click="handleSubmit"
          >
            {{ t('balanceFormWizard.saveModal.save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>
