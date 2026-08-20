<template>
  <div class="login-atrium">
    <div
      class="login-atrium__backdrop"
      aria-hidden="true"
    >
      <b-img
        :src="heroImage"
        alt=""
        class="login-atrium__photo"
      />
      <div class="login-atrium__vignette" />
      <p class="login-atrium__photo-credit">
        {{ heroCredit }}
      </p>
    </div>

    <main class="login-atrium__stage">
      <section
        class="login-atrium__card"
        aria-labelledby="forgot-heading"
      >
        <header class="login-atrium__card-head">
          <b-img
            :src="appLogoImage"
            alt="Sondek"
            class="login-atrium__logo"
          />
          <h1
            id="forgot-heading"
            class="login-atrium__headline"
          >
            Recuperar acceso
          </h1>
          <p class="login-atrium__subhead">
            Te enviaremos un enlace para restablecer tu contraseña
          </p>
        </header>

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
              class="login-atrium__submit"
              :disabled="invalid || isLoading"
            >
              <b-spinner
                v-if="isLoading"
                small
                class="login-atrium__spinner"
              />
              <span>{{ isLoading ? 'Enviando…' : 'Enviar enlace' }}</span>
            </button>

            <button
              type="button"
              class="login-atrium__back"
              @click="$router.push('/login')"
            >
              <feather-icon
                icon="ArrowLeftIcon"
                size="16"
              />
              Volver al login
            </button>
          </b-form>
        </validation-observer>
      </section>

      <p class="login-atrium__legal">
        sondek.cl · gestión de balances metalúrgicos
      </p>
    </main>
  </div>
</template>

<script>
import { ValidationProvider, ValidationObserver } from 'vee-validate'
import { BImg, BForm, BSpinner } from 'bootstrap-vue'
import { required, email } from '@validations'
import ToastificationContent from '@core/components/toastification/ToastificationContent.vue'
import { $themeConfig } from '@themeConfig'
import { pickMiningHero } from '@/assets/images/pages/cmp/miningHeroes'

export default {
  components: {
    BImg,
    BForm,
    BSpinner,
    ValidationProvider,
    ValidationObserver,
  },
  setup() {
    const { appLogoImage } = $themeConfig.app
    return { appLogoImage }
  },
  data() {
    const hero = pickMiningHero()
    return {
      userEmail: '',
      heroImage: hero.src,
      heroCredit: hero.credit,
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

.login-atrium__back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  width: 100%;
  margin-top: 1rem;
  padding: 0.5rem;
  border: 0;
  background: none;
  color: var(--atrium-muted);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: color 140ms ease-out;

  &:hover,
  &:focus {
    color: var(--atrium-text);
    outline: none;
  }
}
</style>
