<template>
  <div class="auth-wrapper auth-v2">
    <b-row class="auth-inner m-0">

      <!-- Brand logo-->
      <div class="brand-logo-container">
        <b-link class="brand-logo">
          <b-img
            :src="appLogoImage"
            alt="logo"
            class="brand-logo-img"
          />
        </b-link>
      </div>
      <!-- /Brand logo-->

      <!-- Left Text-->
      <b-col
        lg="8"
        class="d-none d-lg-flex align-items-center p-0"
      >
        <div class="hero-section">
          <div class="hero-overlay"></div>
          <b-img
            :src="imgUrl"
            alt="Login V2"
            class="hero-image"
          />
          <div class="hero-content">
            <h1 class="hero-title">Sistema de Gestión Minera</h1>
            <p class="hero-subtitle">Control y administración integral de operaciones mineras</p>
          </div>
        </div>
      </b-col>
      <!-- /Left Text-->

      <!-- Login-->
      <b-col
        lg="4"
        class="d-flex align-items-center auth-bg px-2 p-lg-5"
      >
        <b-col
          sm="8"
          md="6"
          lg="12"
          class="px-xl-2 mx-auto"
        >
          <div class="login-header">
            <b-card-title
              class="mb-1 font-weight-bold"
              title-tag="h2"
            >
              Bienvenido al sistema! 👋
            </b-card-title>
            <b-card-text class="mb-2">
              Por favor inicie sesión
            </b-card-text>
          </div>



          <!-- form -->
          <validation-observer
            ref="loginForm"
            #default="{invalid}"
          >
            <b-form
              class="auth-login-form mt-2"
              @submit.prevent="login"
            >
              <!-- email -->
              <b-form-group
                label="Email"
                label-for="login-email"
              >
                <validation-provider
                  #default="{ errors }"
                  name="Email"
                  vid="email"
                  rules="required|email"
                >
                  <b-form-input
                    id="login-email"
                    v-model="userEmail"
                    :state="errors.length > 0 ? false:null"
                    name="login-email"
                    placeholder="john@example.com"
                  />
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>

              <!-- forgot password -->
              <b-form-group>
                <validation-provider
                  #default="{ errors }"
                  name="Password"
                  vid="password"
                  rules="required"
                >
                  <b-input-group
                    class="input-group-merge"
                    :class="errors.length > 0 ? 'is-invalid':null"
                  >
                    <b-form-input
                      id="login-password"
                      v-model="password"
                      :state="errors.length > 0 ? false:null"
                      class="form-control-merge"
                      :type="passwordFieldType"
                      name="login-password"
                      placeholder="Password"
                    />
                    <b-input-group-append is-text>
                      <feather-icon
                        class="cursor-pointer"
                        :icon="passwordToggleIcon"
                        @click="togglePasswordVisibility"
                      />
                    </b-input-group-append>
                  </b-input-group>
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>

              <!-- forgot password link -->
              <div class="d-flex justify-content-between align-items-center mb-2">
                <b-form-checkbox
                  id="remember-me"
                  v-model="status"
                  name="checkbox-1"
                >
                  Recuerdame
                </b-form-checkbox>
                <b-link
                  class="text-white"
                  @click="$router.push('/forgot-password')"
                >
                  ¿Olvidaste tu contraseña?
                </b-link>
              </div>

              <!-- submit buttons -->
              <b-button
                type="submit"
                variant="primary"
                block
                :disabled="invalid || isLoading"
              >
                <b-spinner
                  v-if="isLoading"
                  small
                  class="mr-1"
                />
                {{ isLoading ? 'Iniciando sesión...' : 'Iniciar sesión' }}
              </b-button>
            </b-form>
          </validation-observer>


        </b-col>
      </b-col>
    <!-- /Login-->
    </b-row>
  </div>
</template>

<script>
/* eslint-disable global-require */
import { ValidationProvider, ValidationObserver } from 'vee-validate'
import {
  BRow,
  BCol,
  BLink,
  BFormGroup,
  BFormInput,
  BInputGroupAppend,
  BInputGroup,
  BFormCheckbox,
  BCardText,
  BCardTitle,
  BImg,
  BForm,
  BButton,
  BSpinner,
} from 'bootstrap-vue'
import { required, email } from '@validations'
import { togglePasswordVisibility } from '@core/mixins/ui/forms'
import store from '@/store/index'
import { getHomeRouteForLoggedInUser } from '@/auth/utils'

import ToastificationContent from '@core/components/toastification/ToastificationContent.vue'
import { $themeConfig } from '@themeConfig'
import axios from 'axios'
export default {
  components: {
    BRow,
    BCol,
    BLink,
    BFormGroup,
    BFormInput,
    BInputGroupAppend,
    BInputGroup,
    BFormCheckbox,
    BCardText,
    BCardTitle,
    BImg,
    BForm,
    BButton,
    BSpinner,
    ValidationProvider,
    ValidationObserver,
  },
  setup() {
    // App Name
    const { appLogoImage } = $themeConfig.app
    return {
      appLogoImage,
    }
  },
  mixins: [togglePasswordVisibility],
  data() {
    return {
      status: '',
      password: '',
      userEmail: '',
      sideImg: require('@/assets/images/pages/cmp/cmp1.jpg'),
      isLoading: false,

      // validation rules
      required,
      email,
    }
  },
  computed: {
    passwordToggleIcon() {
      return this.passwordFieldType === 'password' ? 'EyeIcon' : 'EyeOffIcon'
    },
    imgUrl() {
      if (store.state.appConfig.layout.skin === 'dark') {
        // eslint-disable-next-line vue/no-side-effects-in-computed-properties
        this.sideImg = require('@/assets/images/pages/cmp/cmp1.jpg')
        return this.sideImg
      }
      return this.sideImg
    },
  },
  methods: {
    login() {
        this.isLoading = true;
        this.$http.get('/sanctum/csrf-cookie').then(response => {
        this.$http.post('/api/auth/login', {
              email: this.userEmail,
              password: this.password,
            }).then(response => {
                const { userData } = response.data
            console.log('User signed in!', userData);
            localStorage.setItem('userData', JSON.stringify(userData))
            this.$ability.update(userData.ability)

            this.$router.replace(getHomeRouteForLoggedInUser(userData.role)).then(() => {
                this.$toast({
                  component: ToastificationContent,
                  position: 'top-right',
                  props: {
                    title: `Bienvenido ${userData.fullName || userData.username}`,
                    icon: 'CoffeeIcon',
                    variant: 'success',
                    text: `Has iniciado sesion correctamente`,
                  },
                })
              })
        }).catch(error => {
            console.log(error);
            // Mostrar alert de error cuando el login falla
            this.$toast({
              component: ToastificationContent,
              position: 'top-right',
              props: {
                title: 'Error de autenticación',
                icon: 'AlertTriangleIcon',
                variant: 'danger',
                text: 'Credenciales incorrectas. Por favor, verifica tu email y contraseña.',
              },
            })
        }).finally(() => {
            this.isLoading = false;
        });
    });
    },
  },
}
</script>

<style lang="scss">
@import '~@core/scss/vue/pages/page-auth.scss';

// Estilos modernos para el login
.auth-wrapper {
  min-height: 100vh;
  background: #f8f9fa;
}

.auth-inner {
  min-height: 100vh;
}

// Logo moderno
.brand-logo-container {
  position: absolute;
  top: 2rem;
  left: 2rem;
  z-index: 10;

  .brand-logo {
    display: block;

    .brand-logo-img {
      height: 60px;
      width: auto;
      filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));
      transition: all 0.3s ease;

      &:hover {
        transform: scale(1.05);
        filter: drop-shadow(0 6px 12px rgba(0, 0, 0, 0.2));
      }
    }
  }
}

// Sección hero moderna
.hero-section {
  position: relative;
  width: 100%;
  height: 100vh;
  overflow: hidden;

  .hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
      135deg,
      rgba(0, 0, 0, 0.6) 0%,
      rgba(0, 0, 0, 0.4) 50%,
      rgba(0, 0, 0, 0.7) 100%
    );
    z-index: 2;
  }

  .hero-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 1;
  }

  .hero-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: white;
    z-index: 3;
    max-width: 600px;
    padding: 2rem;

         .hero-title {
       font-size: 3.5rem;
       font-weight: 700;
       margin-bottom: 1.5rem;
       text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
       color: white;
       letter-spacing: -0.02em;
     }

    .hero-subtitle {
      font-size: 1.25rem;
      font-weight: 300;
      opacity: 0.95;
      text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
      line-height: 1.6;
      letter-spacing: 0.01em;
    }
  }
}

// Formulario de login moderno
.auth-bg {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  position: relative;
  box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1);

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.1;
  }
}

.login-header {
  .card-title {
    color: white;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .card-text {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    font-weight: 300;
  }
}

// Mejoras en los campos del formulario
.form-group {
  margin-bottom: 1.5rem;

  label {
    color: white;
    font-weight: 500;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .form-control {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    color: white;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;

    &::placeholder {
      color: rgba(255, 255, 255, 0.6);
    }

    &:focus {
      background: rgba(255, 255, 255, 0.15);
      border-color: rgba(255, 255, 255, 0.5);
      box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
      color: white;
    }

    &.is-invalid {
      border-color: #ff6b6b;
      background: rgba(255, 107, 107, 0.1);
    }
  }
}

// Botón moderno
.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 16px;
  padding: 0.875rem 2.5rem;
  font-weight: 600;
  font-size: 1rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.2);
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
  }

  &:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);

    &::before {
      left: 100%;
    }
  }

  &:focus {
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25), 0 4px 15px rgba(102, 126, 234, 0.4);
    border-color: rgba(255, 255, 255, 0.6);
  }

  &:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
  }

  &:disabled {
    opacity: 0.7;
    transform: none;
    border-color: rgba(255, 255, 255, 0.1);
  }
}

// Checkbox moderno
.custom-control-input:checked ~ .custom-control-label::before {
  background-color: #667eea;
  border-color: #667eea;
}

.custom-control-label {
  color: rgba(255, 255, 255, 0.9);
  font-weight: 400;
}

// Responsive design
@media (max-width: 991.98px) {
  .brand-logo-container {
    position: relative;
    top: auto;
    left: auto;
    text-align: center;
    margin-bottom: 2rem;

    .brand-logo-img {
      height: 50px;
    }
  }

  .hero-content {
    .hero-title {
      font-size: 2.5rem;
    }

    .hero-subtitle {
      font-size: 1.1rem;
    }
  }

  .login-header {
    .card-title {
      font-size: 2rem;
    }
  }
}

@media (max-width: 575.98px) {
  .hero-content {
    .hero-title {
      font-size: 2rem;
    }

    .hero-subtitle {
      font-size: 1rem;
    }
  }

  .login-header {
    .card-title {
      font-size: 1.75rem;
    }
  }
}
</style>
