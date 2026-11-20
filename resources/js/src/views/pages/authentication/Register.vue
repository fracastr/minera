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
            alt="Register V2"
            class="hero-image"
          />
          <div class="hero-content">
            <h1 class="hero-title">Sistema de Gestión Minera</h1>
            <p class="hero-subtitle">Control y administración integral de operaciones mineras</p>
          </div>
        </div>
      </b-col>
      <!-- /Left Text-->

      <!-- Register-->
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
              Crea tu cuenta! 🚀
            </b-card-title>
            <b-card-text class="mb-2">
              Regístrate como nuevo usuario
            </b-card-text>
          </div>

          <!-- form -->
          <validation-observer
            ref="registerForm"
            #default="{invalid}"
          >
            <b-form
              class="auth-register-form mt-2"
              @submit.prevent="register"
            >
              <!-- username -->
              <b-form-group
                label="Nombre de usuario"
                label-for="register-username"
              >
                <validation-provider
                  #default="{ errors }"
                  name="Username"
                  vid="username"
                  rules="required"
                >
                  <b-form-input
                    id="register-username"
                    v-model="username"
                    :state="errors.length > 0 ? false:null"
                    name="register-username"
                    placeholder="johndoe"
                  />
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>

              <!-- email -->
              <b-form-group
                label="Email"
                label-for="register-email"
              >
                <validation-provider
                  #default="{ errors }"
                  name="Email"
                  vid="email"
                  rules="required|email"
                >
                  <b-form-input
                    id="register-email"
                    v-model="userEmail"
                    :state="errors.length > 0 ? false:null"
                    name="register-email"
                    placeholder="john@example.com"
                  />
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>

              <!-- password -->
              <b-form-group
                label="Contraseña"
                label-for="register-password"
              >
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
                      id="register-password"
                      v-model="password"
                      :state="errors.length > 0 ? false:null"
                      class="form-control-merge"
                      :type="passwordFieldType"
                      name="register-password"
                      placeholder="············"
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

              <!-- terms checkbox -->
              <b-form-group>
                <b-form-checkbox
                  id="register-privacy-policy"
                  v-model="status"
                  name="checkbox-1"
                >
                  Acepto los
                  <b-link>términos y condiciones</b-link>
                </b-form-checkbox>
              </b-form-group>

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
                {{ isLoading ? 'Creando cuenta...' : 'Crear cuenta' }}
              </b-button>
            </b-form>
          </validation-observer>

          <!-- login link -->
          <p class="text-center mt-3">
            <span class="text-white">¿Ya tienes una cuenta?</span>
            <b-link
              class="text-white font-weight-bold"
              @click="$router.push('/login')"
            >
              <span>&nbsp;Inicia sesión</span>
            </b-link>
          </p>

        </b-col>
      </b-col>
    <!-- /Register-->
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
import ToastificationContent from '@core/components/toastification/ToastificationContent.vue'
import { $themeConfig } from '@themeConfig'
import store from '@/store/index'

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
      username: '',
      userEmail: '',
      password: '',
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
    register() {
      this.isLoading = true
      this.$http.post('/api/auth/register', {
        name: this.username,
        email: this.userEmail,
        password: this.password,
        c_password: this.password,
      }).then(response => {
          console.log('Usuario registrado!', response.data)
          this.$toast({
            component: ToastificationContent,
            position: 'top-right',
            props: {
              title: 'Usuario creado con éxito',
              icon: 'CheckCircleIcon',
              variant: 'success',
              text: 'Tu cuenta ha sido creada correctamente. Puedes iniciar sesión ahora.',
            },
          })
          this.$router.push('/login')
        }).catch(error => {
          console.log(error)
          this.$toast({
            component: ToastificationContent,
            position: 'top-right',
            props: {
              title: 'Error al crear cuenta',
              icon: 'AlertTriangleIcon',
              variant: 'danger',
              text: 'Hubo un error al crear tu cuenta. Por favor, intenta nuevamente.',
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
@import '~@core/scss/vue/pages/page-auth.scss';

// Estilos modernos para el registro
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

// Formulario de registro moderno
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

// Enlaces de texto
.text-white {
  color: white !important;
}

.font-weight-bold {
  font-weight: 700 !important;
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
