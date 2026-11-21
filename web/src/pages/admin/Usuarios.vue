<script setup lang="ts">
import { Check, LoaderCircle, Pencil, Search, Trash2, UserPlus, X } from '@lucide/vue'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { computed, onMounted, reactive, ref } from 'vue'
import Notice from '@/components/Notice.vue'
import TextField from '@/components/TextField.vue'
import { api, errorMessage, fieldError } from '@/lib/api'
import { ask, notify } from '@/lib/feedback'
import { fecha } from '@/lib/format'
import { passwordRules } from '@/lib/passwordRules'
import { useAuthStore } from '@/stores/auth'

type Rol = 'admin' | 'operator' | 'viewer'
interface Usuario {
  id: number
  nombre: string
  email: string
  role: Rol
  activo: boolean
  dashboard_access: boolean
  created_at: string | null
}

const auth = useAuthStore()
const usuarios = ref<Usuario[]>([])
const loading = ref(true)
const error = ref('')

async function load() {
  try {
    usuarios.value = await api<Usuario[]>('/users')
    error.value = ''
  } catch (e) {
    error.value = errorMessage(e, 'No se pudo cargar la lista de usuarios.')
  } finally {
    loading.value = false
  }
}
onMounted(load)

const ROLES: Record<Rol, { label: string; detalle: string }> = {
  admin: { label: 'Administrador', detalle: 'Todo, incluidos usuarios y analytics' },
  operator: { label: 'Operador', detalle: 'Crea, corre y guarda balances' },
  viewer: { label: 'Lector', detalle: 'Solo consulta balances' },
}

// Filters (client side: the list is a few dozen people).
const q = ref('')
const rol = ref<'' | Rol>('')
const estado = ref<'' | 'activos' | 'inactivos'>('')
const norm = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const filas = computed(() => usuarios.value.filter(u =>
  (!q.value || norm(`${u.nombre} ${u.email}`).includes(norm(q.value)))
  && (!rol.value || u.role === rol.value)
  && (!estado.value || u.activo === (estado.value === 'activos'))))
const hayFiltros = computed(() => !!(q.value || rol.value || estado.value))
function limpiar() {
  q.value = ''
  rol.value = ''
  estado.value = ''
}

// Create / edit dialog
const open = ref(false)
const editando = ref<Usuario | null>(null)
const form = reactive({ name: '', email: '', role: 'operator' as Rol, activo: true, dashboard_access: false, password: '', password_confirmation: '' })
const busy = ref(false)
const tried = ref(false)
const serverError = ref<unknown>(null)

function abrir(u: Usuario | null) {
  editando.value = u
  Object.assign(form, {
    name: u?.nombre ?? '', email: u?.email ?? '', role: u?.role ?? 'operator', activo: u?.activo ?? true,
    dashboard_access: u?.dashboard_access ?? false, password: '', password_confirmation: '',
  })
  tried.value = false
  serverError.value = null
  open.value = true
}

const checks = computed(() => passwordRules.map(r => ({ label: r.label, ok: r.test(form.password) })))
const pideClave = computed(() => !editando.value || form.password !== '')
const errors = computed(() => ({
  name: !form.name.trim() ? 'Indica el nombre.' : '',
  email: !/^\S+@\S+\.\S+$/.test(form.email.trim()) ? 'Indica un correo válido.' : '',
  password: pideClave.value && !checks.value.every(c => c.ok) ? 'La contraseña no cumple todos los requisitos.' : '',
  password_confirmation: pideClave.value && form.password_confirmation !== form.password ? 'Las contraseñas no coinciden.' : '',
}))
const err = (k: keyof typeof errors.value) => (tried.value && errors.value[k]) || fieldError(serverError.value, k) || undefined

async function guardar() {
  tried.value = true
  if (Object.values(errors.value).some(Boolean)) return
  busy.value = true
  serverError.value = null
  const body: Record<string, unknown> = {
    name: form.name.trim(), email: form.email.trim(), role: form.role, activo: form.activo, dashboard_access: form.dashboard_access,
  }
  if (pideClave.value) Object.assign(body, { password: form.password, password_confirmation: form.password_confirmation })
  try {
    const res = await api<{ message: string }>(editando.value ? `/users/${editando.value.id}` : '/users', {
      method: editando.value ? 'PUT' : 'POST',
      body,
    })
    open.value = false
    notify('ok', res.message)
    await load()
  } catch (e) {
    serverError.value = e
  } finally {
    busy.value = false
  }
}
// A 422 with field errors shows next to the fields; anything else as a banner in the dialog.
const errorGeneral = computed(() => {
  const e = serverError.value
  if (!e) return ''
  return ['name', 'email', 'role', 'password'].some(k => fieldError(e, k)) ? '' : errorMessage(e, 'No se pudo guardar el usuario.')
})

async function eliminar(u: Usuario) {
  const ok = await ask('Eliminar usuario', `${u.nombre} (${u.email}) perderá el acceso de inmediato. Sus balances guardados se conservan.`, [
    { value: 'eliminar', label: 'Eliminar', tone: 'danger' },
  ])
  if (!ok) return
  try {
    const res = await api<{ message: string }>(`/users/${u.id}`, { method: 'DELETE' })
    notify('ok', res.message)
    await load()
  } catch (e) {
    notify('danger', errorMessage(e, 'No se pudo eliminar el usuario.'))
  }
}

const label = 'mb-1 block text-xs font-medium text-ink-muted'
const radio = 'flex cursor-pointer items-start gap-2.5 rounded-md border border-line px-3 py-2 has-[:checked]:border-primary has-[:checked]:bg-primary-soft has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-[var(--focus)]'
</script>

<template>
  <div class="mx-auto max-w-6xl">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="font-display text-xl font-semibold">Usuarios</h1>
        <p class="mt-1 text-ink-muted">
          <template v-if="!loading">{{ usuarios.length }} {{ usuarios.length === 1 ? 'cuenta' : 'cuentas' }}, {{ usuarios.filter(u => u.activo).length }} activas</template>
          <template v-else>Cargando…</template>
        </p>
      </div>
      <button type="button" class="btn btn-primary" @click="abrir(null)">
        <UserPlus class="size-4" aria-hidden="true" /> Nuevo usuario
      </button>
    </div>

    <section aria-label="Filtros" class="mt-6 grid grid-cols-2 gap-3 rounded-lg border border-line bg-surface p-3 sm:grid-cols-4">
      <div class="col-span-2">
        <label for="u-q" :class="label">Buscar</label>
        <div class="relative">
          <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-ink-faint" aria-hidden="true" />
          <input id="u-q" v-model="q" type="search" placeholder="Nombre o correo" class="field pl-8">
        </div>
      </div>
      <div>
        <label for="u-rol" :class="label">Rol</label>
        <select id="u-rol" v-model="rol" class="field">
          <option value="">Todos</option>
          <option v-for="(r, k) in ROLES" :key="k" :value="k">{{ r.label }}</option>
        </select>
      </div>
      <div>
        <label for="u-estado" :class="label">Estado</label>
        <select id="u-estado" v-model="estado" class="field">
          <option value="">Todos</option>
          <option value="activos">Activos</option>
          <option value="inactivos">Inactivos</option>
        </select>
      </div>
    </section>

    <Notice v-if="error" tone="danger" class="mt-4">{{ error }}</Notice>

    <div class="mt-4 overflow-x-auto rounded-lg border border-line bg-surface">
      <table class="w-full min-w-[46rem] text-left text-[0.8125rem]">
        <thead class="border-b border-line bg-surface-2/60 text-xs text-ink-muted">
          <tr>
            <th scope="col" class="px-4 py-2.5 font-medium">Nombre</th>
            <th scope="col" class="px-4 py-2.5 font-medium">Rol</th>
            <th scope="col" class="px-4 py-2.5 font-medium">Analytics</th>
            <th scope="col" class="px-4 py-2.5 font-medium">Estado</th>
            <th scope="col" class="px-4 py-2.5 font-medium">Creado</th>
            <th scope="col" class="px-4 py-2.5 font-medium"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="px-4 py-10 text-center text-ink-muted">Cargando usuarios…</td>
          </tr>
          <tr v-else-if="!filas.length">
            <td colspan="6" class="px-4 py-12 text-center">
              <p class="font-medium">{{ hayFiltros ? 'Ningún usuario coincide con los filtros.' : 'Todavía no hay usuarios.' }}</p>
              <button v-if="hayFiltros" type="button" class="mt-2 text-primary hover:underline" @click="limpiar">Limpiar filtros</button>
            </td>
          </tr>
          <tr v-for="u in filas" v-else :key="u.id" class="border-b border-line last:border-0">
            <td class="px-4 py-2.5">
              <p class="font-medium">{{ u.nombre }} <span v-if="u.id === auth.user?.id" class="text-xs font-normal text-ink-faint">(tú)</span></p>
              <p class="text-xs text-ink-muted">{{ u.email }}</p>
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">{{ ROLES[u.role]?.label ?? u.role }}</td>
            <td class="px-4 py-2.5">
              <span v-if="u.role === 'admin' || u.dashboard_access" class="inline-flex items-center gap-1 text-ok">
                <Check class="size-3.5" aria-hidden="true" /> Sí
              </span>
              <span v-else class="text-ink-faint">No</span>
            </td>
            <td class="px-4 py-2.5">
              <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="u.activo ? 'bg-ok-soft text-ok' : 'bg-surface-2 text-ink-muted'">{{ u.activo ? 'Activo' : 'Inactivo' }}</span>
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap text-ink-muted">{{ fecha(u.created_at) }}</td>
            <td class="px-4 py-2.5">
              <div class="flex justify-end gap-1">
                <button type="button" class="grid size-8 place-items-center rounded-md text-ink-muted hover:bg-surface-2 hover:text-ink" :aria-label="`Editar a ${u.nombre}`" @click="abrir(u)">
                  <Pencil class="size-4" aria-hidden="true" />
                </button>
                <button type="button" class="grid size-8 place-items-center rounded-md text-ink-muted hover:bg-danger-soft hover:text-danger" :aria-label="`Eliminar a ${u.nombre}`" @click="eliminar(u)">
                  <Trash2 class="size-4" aria-hidden="true" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <DialogRoot v-model:open="open">
      <DialogPortal>
        <DialogOverlay class="fixed inset-0 z-50 bg-navy/45" />
        <DialogContent class="fixed top-1/2 left-1/2 z-50 max-h-[90dvh] w-[min(32rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg border border-line bg-surface p-5 shadow-[0_16px_48px_rgb(16_20_40/0.22)]">
          <DialogTitle class="font-display text-base font-semibold">{{ editando ? 'Editar usuario' : 'Nuevo usuario' }}</DialogTitle>
          <DialogDescription class="mt-1 text-ink-muted">
            {{ editando ? 'Deja la contraseña en blanco para mantener la actual.' : 'La persona entra con este correo y contraseña.' }}
          </DialogDescription>
          <form class="mt-5 space-y-4" novalidate @submit.prevent="guardar">
            <Notice v-if="errorGeneral" tone="danger">{{ errorGeneral }}</Notice>
            <TextField v-model="form.name" label="Nombre" autocomplete="off" :error="err('name')" required />
            <TextField v-model="form.email" type="email" label="Correo" autocomplete="off" :error="err('email')" required />
            <fieldset>
              <legend class="mb-1.5 text-[0.8125rem] font-medium">Rol</legend>
              <div class="grid gap-2 sm:grid-cols-3">
                <label v-for="(r, k) in ROLES" :key="k" :class="radio">
                  <input v-model="form.role" type="radio" :value="k" class="mt-1 accent-[var(--primary)]">
                  <span>
                    <span class="block text-[0.8125rem] font-medium">{{ r.label }}</span>
                    <span class="block text-xs text-ink-muted">{{ r.detalle }}</span>
                  </span>
                </label>
              </div>
              <p v-if="fieldError(serverError, 'role')" class="mt-1 text-[0.8125rem] text-danger">{{ fieldError(serverError, 'role') }}</p>
            </fieldset>
            <div class="space-y-2">
              <label class="flex items-center gap-2.5">
                <input v-model="form.activo" type="checkbox" class="size-4 accent-[var(--primary)]">
                <span>Cuenta activa <span class="text-ink-muted">(una cuenta inactiva no puede entrar)</span></span>
              </label>
              <label class="flex items-center gap-2.5" :class="{ 'text-ink-muted': form.role === 'admin' }">
                <input v-model="form.dashboard_access" type="checkbox" class="size-4 accent-[var(--primary)]" :disabled="form.role === 'admin'">
                <span>Acceso a Analytics <span v-if="form.role === 'admin'">(los administradores siempre lo tienen)</span></span>
              </label>
            </div>
            <div class="space-y-3 border-t border-line pt-4">
              <TextField
                v-model="form.password" type="password" autocomplete="new-password" :required="!editando"
                :label="editando ? 'Nueva contraseña (opcional)' : 'Contraseña'" :error="err('password')"
              />
              <ul v-if="pideClave" class="grid gap-x-4 gap-y-1 text-xs sm:grid-cols-2" aria-label="Requisitos de la contraseña">
                <li v-for="c in checks" :key="c.label" class="flex items-center gap-1.5" :class="c.ok ? 'text-ok' : 'text-ink-muted'">
                  <component :is="c.ok ? Check : X" class="size-3.5 shrink-0" aria-hidden="true" />
                  {{ c.label }}<span class="sr-only">{{ c.ok ? ': cumple' : ': falta' }}</span>
                </li>
              </ul>
              <TextField
                v-if="pideClave" v-model="form.password_confirmation" type="password" autocomplete="new-password"
                label="Confirmar contraseña" :error="err('password_confirmation')" required
              />
            </div>
            <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
              <DialogClose class="btn btn-quiet" type="button">Cancelar</DialogClose>
              <button type="submit" class="btn btn-primary" :disabled="busy">
                <LoaderCircle v-if="busy" class="size-4 animate-spin" aria-hidden="true" />
                {{ editando ? 'Guardar cambios' : 'Crear usuario' }}
              </button>
            </div>
          </form>
        </DialogContent>
      </DialogPortal>
    </DialogRoot>
  </div>
</template>
