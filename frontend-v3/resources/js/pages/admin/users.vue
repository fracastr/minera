<script setup>
definePage({
  meta: {
    action: 'manage',
    subject: 'Users',
  },
})

const { t, te } = useI18n()
const { success, error } = useNotify()

const emptyForm = () => ({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'viewer',
  activo: true,
  dashboard_access: false,
})

const users = ref([])
const loading = ref(false)
const saving = ref(false)
const showModal = ref(false)
const isEditing = ref(false)
const editingUserId = ref(null)
const searchQuery = ref('')
const form = ref(emptyForm())
const formRef = ref()
const deleteTarget = ref(null)

const headers = [
  { key: 'id', title: 'ID', align: 'center' },
  { key: 'nombre', title: 'Nombre' },
  { key: 'email', title: 'Email' },
  { key: 'role', title: 'Rol' },
  { key: 'activo', title: 'Estado' },
  { key: 'dashboard_access', title: 'Dashboard' },
  { key: 'created_at', title: 'Creado' },
  { key: 'actions', title: 'Acciones', sortable: false },
]

const roleOptions = computed(() => [
  { value: 'admin', title: t('roles.admin') },
  { value: 'operator', title: t('roles.operator') },
  { value: 'viewer', title: t('roles.viewer') },
])

const hasDashboardAccess = user => user.dashboard_access === true || user.dashboard_access === 1 || user.dashboard_access === '1'
const roleLabel = role => (te(`roles.${role}`) ? t(`roles.${role}`) : role)
const roleColor = role => ({ admin: 'error', operator: 'warning', viewer: 'info' })[role] || 'secondary'
const formatDate = value => (value ? new Date(value).toLocaleDateString('es-CL') : '-')
const isCurrentUser = user => useCookie('userData').value?.id === user.id

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return users.value

  return users.value.filter(user => [
    user.id,
    user.nombre,
    user.email,
    user.role,
    roleLabel(user.role),
    user.activo !== false ? 'activo' : 'inactivo',
    hasDashboardAccess(user) ? 'si dashboard' : 'no dashboard',
  ].join(' ').toLowerCase().includes(query))
})

// Password is optional when editing; when present it must pass the same rules as the server.
const passwordRules = computed(() => (isEditing.value ? [v => !v || strongPasswordRule(v)] : [requiredRule, strongPasswordRule]))
const confirmRules = computed(() => [v => (v || '') === (form.value.password || '') || 'Las contraseñas no coinciden.'])

async function loadUsers() {
  loading.value = true
  try {
    users.value = await $api('/users')
  }
  catch (e) {
    error(apiErrorMessage(e, 'No se pudieron cargar los usuarios.'), { title: 'Error' })
  }
  finally {
    loading.value = false
  }
}

function openCreateModal() {
  isEditing.value = false
  editingUserId.value = null
  form.value = emptyForm()
  showModal.value = true
}

function openEditModal(user) {
  isEditing.value = true
  editingUserId.value = user.id
  form.value = {
    name: user.nombre,
    email: user.email,
    password: '',
    password_confirmation: '',
    role: user.role || 'viewer',
    activo: user.activo !== false,
    dashboard_access: hasDashboardAccess(user),
  }
  showModal.value = true
}

async function saveUser() {
  if (!(await formRef.value.validate()).valid) return
  if (form.value.password !== form.value.password_confirmation) {
    error('Las contraseñas no coinciden.', { title: 'Error' })

    return
  }

  const payload = {
    name: form.value.name,
    email: form.value.email,
    role: form.value.role,
    activo: form.value.activo,
    dashboard_access: form.value.dashboard_access,
  }

  if (form.value.password) {
    payload.password = form.value.password
    payload.password_confirmation = form.value.password_confirmation
  }
  else if (!isEditing.value) {
    error('La contraseña es obligatoria.', { title: 'Error' })

    return
  }

  saving.value = true
  try {
    const res = isEditing.value
      ? await $api(`/users/${editingUserId.value}`, { method: 'PUT', body: payload })
      : await $api('/users', { method: 'POST', body: payload })

    success(res?.message || 'Usuario guardado correctamente.', { title: 'Éxito' })
    showModal.value = false
    loadUsers()
  }
  catch (e) {
    error(apiErrorMessage(e, 'No se pudo guardar el usuario.'), { title: 'Error' })
  }
  finally {
    saving.value = false
  }
}

async function deleteUser() {
  const user = deleteTarget.value
  deleteTarget.value = null
  try {
    const res = await $api(`/users/${user.id}`, { method: 'DELETE' })

    success(res?.message || 'Usuario eliminado.', { title: 'Éxito' })
    loadUsers()
  }
  catch (e) {
    error(apiErrorMessage(e, 'No se pudo eliminar el usuario.'), { title: 'Error' })
  }
}

onMounted(loadUsers)
</script>

<template>
  <VCard title="Gestión de usuarios">
    <VCardText>
      <p class="text-medium-emphasis mb-4">
        Crear y administrar cuentas del sistema. Solo visible para administradores.
      </p>

      <div class="d-flex flex-wrap justify-space-between align-center gap-4">
        <AppTextField
          v-model="searchQuery"
          type="search"
          placeholder="Buscar por nombre, email o rol…"
          prepend-inner-icon="tabler-search"
          style="max-inline-size: 320px;"
          clearable
        />
        <VBtn
          prepend-icon="tabler-user-plus"
          @click="openCreateModal"
        >
          Nuevo usuario
        </VBtn>
      </div>
    </VCardText>

    <VDataTable
      :headers="headers"
      :items="filteredUsers"
      :loading="loading"
      :items-per-page="-1"
      loading-text="Cargando..."
      :no-data-text="searchQuery.trim() ? 'No se encontraron usuarios con esa búsqueda' : 'No hay usuarios registrados'"
      hide-default-footer
      hover
    >
      <template #item.role="{ item }">
        <VChip
          :color="roleColor(item.role)"
          size="small"
          label
        >
          {{ roleLabel(item.role) }}
        </VChip>
      </template>

      <template #item.activo="{ item }">
        <VChip
          :color="item.activo !== false ? 'success' : 'error'"
          size="small"
          label
        >
          {{ item.activo !== false ? 'Activo' : 'Inactivo' }}
        </VChip>
      </template>

      <template #item.dashboard_access="{ item }">
        <VChip
          :color="hasDashboardAccess(item) ? 'success' : 'error'"
          size="small"
          label
        >
          {{ hasDashboardAccess(item) ? 'Sí' : 'No' }}
        </VChip>
      </template>

      <template #item.created_at="{ item }">
        {{ formatDate(item.created_at) }}
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-2">
          <VBtn
            size="small"
            variant="outlined"
            @click="openEditModal(item)"
          >
            Editar
          </VBtn>
          <VBtn
            size="small"
            variant="outlined"
            color="error"
            :disabled="isCurrentUser(item)"
            @click="deleteTarget = item"
          >
            Eliminar
          </VBtn>
        </div>
      </template>
    </VDataTable>
  </VCard>

  <!-- Create / edit -->
  <VDialog
    v-model="showModal"
    max-width="560"
  >
    <VCard :title="isEditing ? 'Editar usuario' : 'Nuevo usuario'">
      <DialogCloseBtn @click="showModal = false" />
      <VForm
        ref="formRef"
        @submit.prevent="saveUser"
      >
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.name"
                label="Nombre"
                placeholder="Nombre completo"
                :rules="[requiredRule]"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.email"
                label="Email"
                type="email"
                placeholder="usuario@empresa.cl"
                :rules="[requiredRule, emailRule]"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.password"
                :label="isEditing ? 'Nueva contraseña (opcional)' : 'Contraseña'"
                type="password"
                autocomplete="new-password"
                placeholder="Mínimo 12 caracteres"
                hint="Debe incluir mayúsculas, minúsculas, números y un carácter especial."
                persistent-hint
                :rules="passwordRules"
              />
            </VCol>
            <VCol
              v-if="form.password || !isEditing"
              cols="12"
            >
              <AppTextField
                v-model="form.password_confirmation"
                :label="isEditing ? 'Confirmar nueva contraseña' : 'Confirmar contraseña'"
                type="password"
                autocomplete="new-password"
                placeholder="Repite la contraseña"
                :rules="[requiredRule, ...confirmRules]"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="form.role"
                label="Rol"
                :items="roleOptions"
                :rules="[requiredRule]"
              />
            </VCol>
            <VCol cols="12">
              <VCheckbox
                v-model="form.activo"
                label="Usuario activo"
              />
              <VCheckbox
                v-model="form.dashboard_access"
                label="Acceso al dashboard de analytics"
                hint="Los administradores siempre tienen acceso. Para otros roles, habilita esta opción."
                persistent-hint
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardText class="d-flex justify-end gap-3">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="showModal = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            type="submit"
            :loading="saving"
          >
            Guardar
          </VBtn>
        </VCardText>
      </VForm>
    </VCard>
  </VDialog>

  <!-- Delete confirmation -->
  <VDialog
    :model-value="!!deleteTarget"
    max-width="420"
    @update:model-value="v => { if (!v) deleteTarget = null }"
  >
    <VCard title="Confirmar eliminación">
      <VCardText>
        ¿Eliminar al usuario {{ deleteTarget?.nombre }} ({{ deleteTarget?.email }})?
      </VCardText>
      <VCardText class="d-flex justify-end gap-3">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="deleteTarget = null"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="error"
          @click="deleteUser"
        >
          Eliminar
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
