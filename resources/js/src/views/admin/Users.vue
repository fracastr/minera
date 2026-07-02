<template>
  <div>
    <b-card title="Gestión de usuarios">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <p class="text-muted mb-0">
          Crear y administrar cuentas del sistema. Solo visible para administradores.
        </p>
        <b-button
          variant="primary"
          @click="openCreateModal"
        >
          <feather-icon
            icon="UserPlusIcon"
            class="mr-50"
          />
          Nuevo usuario
        </b-button>
      </div>

      <b-table
        :items="users"
        :fields="tableFields"
        :busy="loading"
        responsive
        striped
        hover
        show-empty
        empty-text="No hay usuarios registrados"
      >
        <template #table-busy>
          <div class="text-center text-primary my-2">
            <b-spinner class="align-middle mr-1" />
            Cargando...
          </div>
        </template>

        <template #cell(role)="data">
          <b-badge :variant="roleVariant(data.item.role)">
            {{ roleLabel(data.item.role) }}
          </b-badge>
        </template>

        <template #cell(activo)="data">
          <b-badge :variant="data.item.activo !== false ? 'light-success' : 'light-danger'">
            {{ data.item.activo !== false ? 'Activo' : 'Inactivo' }}
          </b-badge>
        </template>

        <template #cell(created_at)="data">
          {{ formatDate(data.item.created_at) }}
        </template>

        <template #cell(actions)="data">
          <b-button
            size="sm"
            variant="outline-primary"
            class="mr-50"
            @click="openEditModal(data.item)"
          >
            Editar
          </b-button>
          <b-button
            size="sm"
            variant="outline-danger"
            :disabled="isCurrentUser(data.item)"
            @click="confirmDelete(data.item)"
          >
            Eliminar
          </b-button>
        </template>
      </b-table>
    </b-card>

    <b-modal
      id="user-form-modal"
      v-model="showModal"
      :title="isEditing ? 'Editar usuario' : 'Nuevo usuario'"
      ok-title="Guardar"
      cancel-title="Cancelar"
      :ok-disabled="saving"
      @ok.prevent="saveUser"
    >
      <b-form @submit.prevent="saveUser">
        <b-form-group label="Nombre">
          <b-form-input
            v-model="form.name"
            required
            placeholder="Nombre completo"
          />
        </b-form-group>

        <b-form-group label="Email">
          <b-form-input
            v-model="form.email"
            type="email"
            required
            placeholder="usuario@empresa.cl"
          />
        </b-form-group>

        <b-form-group :label="isEditing ? 'Nueva contraseña (opcional)' : 'Contraseña'">
          <b-form-input
            v-model="form.password"
            type="password"
            :required="!isEditing"
            minlength="12"
            placeholder="Mínimo 12 caracteres"
          />
          <small class="text-muted">
            Debe incluir mayúsculas, minúsculas, números y un carácter especial.
          </small>
        </b-form-group>

        <b-form-group
          v-if="form.password || !isEditing"
          :label="isEditing ? 'Confirmar nueva contraseña' : 'Confirmar contraseña'"
        >
          <b-form-input
            v-model="form.password_confirmation"
            type="password"
            :required="!isEditing || !!form.password"
            minlength="12"
            placeholder="Repite la contraseña"
          />
        </b-form-group>

        <b-form-group label="Rol">
          <b-form-select
            v-model="form.role"
            :options="localizedRoleOptions"
            required
          />
        </b-form-group>

        <b-form-group>
          <b-form-checkbox v-model="form.activo">
            Usuario activo
          </b-form-checkbox>
        </b-form-group>
      </b-form>

      <template #modal-footer="{ ok, cancel }">
        <b-button
          variant="secondary"
          @click="cancel()"
        >
          Cancelar
        </b-button>
        <b-button
          variant="primary"
          :disabled="saving"
          @click="ok()"
        >
          <b-spinner
            v-if="saving"
            small
            class="mr-50"
          />
          Guardar
        </b-button>
      </template>
    </b-modal>
  </div>
</template>

<script>
import {
  BCard,
  BTable,
  BButton,
  BModal,
  BForm,
  BFormGroup,
  BFormInput,
  BFormSelect,
  BFormCheckbox,
  BSpinner,
  BBadge,
} from 'bootstrap-vue'
import ToastificationContent from '@core/components/toastification/ToastificationContent.vue'
import { getUserData } from '@/auth/utils'

const emptyForm = () => ({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'viewer',
  activo: true,
})

export default {
  components: {
    BCard,
    BTable,
    BButton,
    BModal,
    BForm,
    BFormGroup,
    BFormInput,
    BFormSelect,
    BFormCheckbox,
    BSpinner,
    BBadge,
  },
  data() {
    return {
      users: [],
      loading: false,
      saving: false,
      showModal: false,
      isEditing: false,
      editingUserId: null,
      form: emptyForm(),
      tableFields: [
        { key: 'id', label: 'ID', sortable: true, thClass: 'text-center', tdClass: 'text-center' },
        { key: 'nombre', label: 'Nombre', sortable: true },
        { key: 'email', label: 'Email', sortable: true },
        { key: 'role', label: 'Rol', sortable: true },
        { key: 'activo', label: 'Estado', sortable: true },
        { key: 'created_at', label: 'Creado', sortable: true },
        { key: 'actions', label: 'Acciones' },
      ],
    }
  },
  computed: {
    localizedRoleOptions() {
      return [
        { value: 'admin', text: this.$t('roles.admin') },
        { value: 'operator', text: this.$t('roles.operator') },
        { value: 'viewer', text: this.$t('roles.viewer') },
      ]
    },
  },
  mounted() {
    this.loadUsers()
  },
  methods: {
    loadUsers() {
      this.loading = true
      this.$http.get('/api/users')
        .then(response => {
          this.users = response.data
        })
        .catch(error => {
          this.showToast('Error', this.getErrorMessage(error, 'No se pudieron cargar los usuarios.'), 'danger')
        })
        .finally(() => {
          this.loading = false
        })
    },
    openCreateModal() {
      this.isEditing = false
      this.editingUserId = null
      this.form = emptyForm()
      this.showModal = true
    },
    openEditModal(user) {
      this.isEditing = true
      this.editingUserId = user.id
      this.form = {
        name: user.nombre,
        email: user.email,
        password: '',
        password_confirmation: '',
        role: user.role || 'viewer',
        activo: user.activo !== false,
      }
      this.showModal = true
    },
    saveUser() {
      if (this.form.password !== this.form.password_confirmation) {
        this.showToast('Error', 'Las contraseñas no coinciden.', 'danger')
        return
      }

      this.saving = true

      const payload = {
        name: this.form.name,
        email: this.form.email,
        role: this.form.role,
        activo: this.form.activo,
      }

      if (this.form.password) {
        payload.password = this.form.password
        payload.password_confirmation = this.form.password_confirmation
      } else if (!this.isEditing) {
        this.showToast('Error', 'La contraseña es obligatoria.', 'danger')
        this.saving = false
        return
      }

      const request = this.isEditing
        ? this.$http.put(`/api/users/${this.editingUserId}`, payload)
        : this.$http.post('/api/users', payload)

      request
        .then(response => {
          this.showToast(
            'Éxito',
            response.data.message || 'Usuario guardado correctamente.',
            'success',
          )
          this.showModal = false
          this.loadUsers()
        })
        .catch(error => {
          this.showToast('Error', this.getErrorMessage(error, 'No se pudo guardar el usuario.'), 'danger')
        })
        .finally(() => {
          this.saving = false
        })
    },
    confirmDelete(user) {
      this.$bvModal.msgBoxConfirm(
        `¿Eliminar al usuario ${user.nombre} (${user.email})?`,
        {
          title: 'Confirmar eliminación',
          size: 'sm',
          okVariant: 'danger',
          okTitle: 'Eliminar',
          cancelTitle: 'Cancelar',
          centered: true,
        },
      ).then(confirmed => {
        if (confirmed) {
          this.deleteUser(user)
        }
      })
    },
    deleteUser(user) {
      this.$http.delete(`/api/users/${user.id}`)
        .then(response => {
          this.showToast('Éxito', response.data.message || 'Usuario eliminado.', 'success')
          this.loadUsers()
        })
        .catch(error => {
          this.showToast('Error', this.getErrorMessage(error, 'No se pudo eliminar el usuario.'), 'danger')
        })
    },
    isCurrentUser(user) {
      const currentUser = getUserData()
      return currentUser && currentUser.id === user.id
    },
    roleLabel(role) {
      const key = `roles.${role}`
      return this.$te(key) ? this.$t(key) : role
    },
    roleVariant(role) {
      const variants = {
        admin: 'light-danger',
        operator: 'light-warning',
        viewer: 'light-info',
      }
      return variants[role] || 'light-secondary'
    },
    formatDate(value) {
      if (!value) return '-'
      return new Date(value).toLocaleDateString('es-CL')
    },
    getErrorMessage(error, fallback) {
      if (error.response?.data?.message) {
        return error.response.data.message
      }
      if (error.response?.data?.errors) {
        return Object.values(error.response.data.errors).flat().join(' ')
      }
      return fallback
    },
    showToast(title, text, variant) {
      this.$toast({
        component: ToastificationContent,
        position: 'top-right',
        props: {
          title,
          icon: variant === 'success' ? 'CheckCircleIcon' : 'AlertTriangleIcon',
          variant,
          text,
        },
      })
    },
  },
}
</script>
