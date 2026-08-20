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
        aria-labelledby="login-heading"
      >
        <header class="login-atrium__card-head">
          <b-img
            :src="appLogoImage"
            alt="Sondek"
            class="login-atrium__logo"
          />
          <p class="login-atrium__subhead">
            Balance metalúrgico · operación CMP
          </p>
        </header>

        <validation-observer
          ref="loginForm"
          #default="{ invalid }"
        >
          <b-form
            class="login-atrium__form"
            @submit.prevent="login"
          >
            <div class="login-atrium__field">
              <label
                class="login-atrium__label"
                for="login-email"
              >Correo</label>
              <validation-provider
                #default="{ errors }"
                name="Email"
                vid="email"
                rules="required|email"
              >
                <input
                  id="login-email"
                  v-model="userEmail"
                  type="email"
                  name="login-email"
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

            <div class="login-atrium__field">
              <label
                class="login-atrium__label"
                for="login-password"
              >Contraseña</label>
              <validation-provider
                #default="{ errors }"
                name="Password"
                vid="password"
                rules="required"
              >
                <div class="login-atrium__password-wrap">
                  <input
                    id="login-password"
                    v-model="password"
                    :type="passwordFieldType"
                    name="login-password"
                    autocomplete="current-password"
                    class="login-atrium__input login-atrium__input--password"
                    :class="{ 'login-atrium__input--error': errors.length }"
                    placeholder="••••••••"
                  >
                  <button
                    type="button"
                    class="login-atrium__toggle"
                    :aria-label="passwordFieldType === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña'"
                    @click="togglePasswordVisibility"
                  >
                    <feather-icon
                      :icon="passwordToggleIcon"
                      size="18"
                    />
                  </button>
                </div>
                <p
                  v-if="errors[0]"
                  class="login-atrium__error"
                >
                  {{ errors[0] }}
                </p>
              </validation-provider>
            </div>

            <div class="login-atrium__row">
              <label class="login-atrium__remember">
                <input
                  v-model="status"
                  type="checkbox"
                  name="remember-me"
                >
                <span>Recuérdame</span>
              </label>
              <button
                type="button"
                class="login-atrium__link"
                @click="$router.push('/forgot-password')"
              >
                Olvidé mi contraseña
              </button>
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
              <span>{{ isLoading ? 'Entrando…' : 'Entrar al sistema' }}</span>
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
/* eslint-disable global-require */
import { ValidationProvider, ValidationObserver } from 'vee-validate'
import { BImg, BForm, BSpinner } from 'bootstrap-vue'
import { required, email } from '@validations'
import { togglePasswordVisibility } from '@core/mixins/ui/forms'
import { getHomeRouteForLoggedInUser, safeRouterReplace } from '@/auth/utils'
import { AUTH_TOKEN_KEY } from '@/auth/config'
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
  mixins: [togglePasswordVisibility],
  data() {
    const hero = pickMiningHero()
    return {
      status: false,
      password: '',
      userEmail: '',
      heroImage: hero.src,
      heroCredit: hero.credit,
      isLoading: false,
      required,
      email,
    }
  },
  computed: {
    passwordToggleIcon() {
      return this.passwordFieldType === 'password' ? 'EyeIcon' : 'EyeOffIcon'
    },
  },
  methods: {
    async login() {
      this.isLoading = true
      try {
        const response = await this.$http.post('/api/auth/login', {
          email: this.userEmail,
          password: this.password,
        })
        const { userData, accessToken } = response.data
        localStorage.setItem('userData', JSON.stringify(userData))
        localStorage.setItem(AUTH_TOKEN_KEY, accessToken)
        this.$ability.update(userData.ability)
        await safeRouterReplace(this.$router, getHomeRouteForLoggedInUser(userData.role))
        this.$toast({
          component: ToastificationContent,
          position: 'top-right',
          props: {
            title: `Bienvenido, ${userData.fullName || userData.username}`,
            icon: 'CoffeeIcon',
            variant: 'success',
            text: 'Sesión iniciada',
          },
        })
      } catch (error) {
        let errorMessage = 'Correo o contraseña incorrectos.'
        if (error.response?.data?.message && error.response.data.message !== 'Unauthorized') {
          errorMessage = error.response.data.message
        }
        this.$toast({
          component: ToastificationContent,
          position: 'top-right',
          props: {
            title: 'No se pudo entrar',
            icon: 'AlertTriangleIcon',
            variant: 'danger',
            text: errorMessage,
          },
        })
      } finally {
        this.isLoading = false
      }
    },
  },
}
</script>

<style lang="scss">
@import './login-atrium.scss';
</style>
