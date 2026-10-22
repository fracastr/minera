<template>
  <div class="login-atrium">
    <auth-media />

    <main class="login-atrium__panel">
      <section
        class="login-atrium__card"
        aria-labelledby="forgot-heading"
      >
        <h1
          id="forgot-heading"
          class="login-atrium__headline"
        >
          Recuperar acceso
        </h1>
        <p class="login-atrium__subhead">
          Escribe tu correo y te enviaremos un enlace para restablecer la contraseña.
        </p>

        <validation-observer
          ref="forgotPasswordForm"
          #default="{ invalid }"
        >
          <b-form
            class="login-atrium__form"
            @submit.prevent="sendResetLink"
          >
            <div class="login-atrium__field">
              <label
                class="login-atrium__label"
                for="forgot-email"
              >Correo</label>
              <validation-provider
                #default="{ errors }"
                name="Email"
                vid="email"
                rules="required|email"
              >
                <input
                  id="forgot-email"
                  v-model="userEmail"
                  type="email"
                  name="forgot-email"
                  autocomplete="username"
                  class="login-atrium__input"
                  :class="{ 'login-atrium__input--error': errors.length }"
                  placeholder="nombre@empresa.cl"
                >
                <p
                  v-if="errors[0]"
                  class="login-atrium__error"
                >
                  {{ errors[0] }}
                </p>
              </validation-provider>
            </div>

            <button
              type="submit"
              class="login-atrium__submit login-atrium__submit--spaced"
              :disabled="invalid || isLoading"
            >
              <b-spinner
                v-if="isLoading"
                small
                class="login-atrium__spinner"
              />
              <span>{{ isLoading ? 'Enviando…' : 'Enviar enlace' }}</span>
            </button>
          </b-form>
        </validation-observer>

        <router-link
          :to="{ name: 'auth-login' }"
          class="login-atrium__back"
        >
          Volver al login
        </router-link>
      </section>
    </main>
  </div>
</template>

<script>
import { ValidationProvider, ValidationObserver } from 'vee-validate'
import { BForm, BSpinner } from 'bootstrap-vue'
import { required, email } from '@validations'
import ToastificationContent from '@core/components/toastification/ToastificationContent.vue'
import AuthMedia from './AuthMedia.vue'

export default {
  components: {
    AuthMedia,
    BForm,
    BSpinner,
    ValidationProvider,
    ValidationObserver,
  },
  data() {
    return {
      userEmail: '',
      isLoading: false,
      required,
      email,
    }
  },
  methods: {
    sendResetLink() {
      this.isLoading = true

      this.$http.post('/api/auth/forgot-password', {
        email: this.userEmail,
      }).then(() => {
        this.$toast({
          component: ToastificationContent,
          position: 'top-right',
          props: {
            title: 'Enlace enviado',
            icon: 'CheckCircleIcon',
            variant: 'success',
            text: 'Revisa tu correo para restablecer la contraseña.',
          },
        })
        this.userEmail = ''
        this.$refs.forgotPasswordForm.reset()
      }).catch(error => {
        let errorMessage = 'No se pudo enviar el enlace. Intenta de nuevo.'
        if (error.response?.data?.message) {
          errorMessage = error.response.data.message
        }
        this.$toast({
          component: ToastificationContent,
          position: 'top-right',
          props: {
            title: 'Error',
            icon: 'AlertTriangleIcon',
            variant: 'danger',
            text: errorMessage,
          },
        })
      }).finally(() => {
        this.isLoading = false
      })
    },
  },
}
</script>

<style lang="scss">
@import './login-atrium.scss';
</style>
