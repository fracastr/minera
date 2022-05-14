<template>
  <div>
    <form-wizard
      color="#7367F0"
      :title="null"
      :subtitle="null"
      shape="square"
      finish-button-text="Guardar"
      back-button-text="Anterior"
      next-button-text="Siguiente"
      class="mb-3"
      @on-complete="formSubmitted"
      :disabled="loading"
    >
    <div class="text-center" v-if="loading">
        <b-spinner label="Spinning"></b-spinner>
    </div>
      <!-- accoint details tab -->
      <tab-content
        title="Selección de valle"
        :before-change="validationForm"
      >
        <validation-observer
          ref="accountRules"
          tag="form"
        >
          <b-row>
            <b-col
              cols="12"
              class="mb-2"
            >
              <h5 class="mb-0">
                Sistema de balance CMP
              </h5>
              <small class="text-muted">
                Selección de valle
              </small>
            </b-col>
            <b-col md="12">
              <validation-provider
                #default="{ errors }"
                name="Valle"
                rules="required"
              >
                <b-form-group
                  label="Valle"
                  label-for="valle"
                  :state="errors.length > 0 ? false:null"
                >
                    <b-form-select
                    v-model="valle"
                    :options="valles"
                    @change="valles_change"
                    />
                  <b-form-invalid-feedback :state="errors.length > 0 ? false:null">
                    {{ errors[0] }}
                  </b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
          </b-row>
        </validation-observer>
      </tab-content>

      <tab-content
        title="Selección de proceso"
        :before-change="validationForm"
      >
        <validation-observer
          ref="infoRules"
          tag="form"
        >
          <b-row>
            <b-col
              cols="12"
              class="mb-2"
            >
              <h5 class="mb-0">
                Sistema de balance CMP
              </h5>
              <small class="text-muted">
                Selección de proceso
              </small>
            </b-col>
            <b-col md="12">
              <validation-provider
                #default="{ errors }"
                name="Proceso"
                rules="required"
              >
                <b-form-group
                  label="Proceso"
                  label-for="proceso"
                  :state="errors.length > 0 ? false:null"
                >
                    <b-form-select
                    v-model="proceso"
                    :options="procesos"
                    />
                  <b-form-invalid-feedback :state="errors.length > 0 ? false:null">
                    {{ errors[0] }}
                  </b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
          </b-row>
        </validation-observer>
      </tab-content>

      <!-- personal details tab -->
      <!-- <tab-content
        title="Personal Info"
        :before-change="validationFormInfo"
      >
        <validation-observer
          ref="infoRules"
          tag="form"
        >
          <b-row>
            <b-col
              cols="12"
              class="mb-2"
            >
              <h5 class="mb-0">
                Personal Info
              </h5>
              <small class="text-muted">Enter Your Personal Info.</small>
            </b-col>
            <b-col md="6">
              <b-form-group
                label="First Name"
                label-for="first-name"
              >
                <validation-provider
                  #default="{ errors }"
                  name="First Name"
                  rules="required"
                >
                  <b-form-input
                    id="first-name"
                    v-model="first_name"
                    placeholder="John"
                    :state="errors.length > 0 ? false:null"
                  />
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>
            </b-col>
            <b-col md="6">
              <b-form-group
                label="Last Name"
                label-for="last-name"
              >
                <validation-provider
                  #default="{ errors }"
                  name="Last Name"
                  rules="required"
                >
                  <b-form-input
                    id="last-name"
                    v-model="last_name"
                    :state="errors.length > 0 ? false:null"
                    placeholder="Doe"
                  />
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>
            </b-col>
            <b-col md="6">
              <validation-provider
                #default="{ errors }"
                name="Country"
                rules="required"
              >
                <b-form-group
                  label="Country"
                  label-for="country"
                  :state="errors.length > 0 ? false:null"
                >
                  <v-select
                    id="country"
                    v-model="selectedContry"
                    :dir="$store.state.appConfig.isRTL ? 'rtl' : 'ltr'"
                    :options="countryName"
                    :selectable="option => ! option.value.includes('select_value')"
                    label="text"
                  />
                  <b-form-invalid-feedback :state="errors.length > 0 ? false:null">
                    {{ errors[0] }}
                  </b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
            <b-col md="6">
              <validation-provider
                #default="{ errors }"
                name="Language"
                rules="required"
              >
                <b-form-group
                  label="Language"
                  label-for="language"
                  :state="errors.length > 0 ? false:null"
                >
                  <v-select
                    id="language"
                    v-model="selectedLanguage"
                    :dir="$store.state.appConfig.isRTL ? 'rtl' : 'ltr'"
                    :options="languageName"
                    :selectable="option => ! option.value.includes('nothing_selected')"
                    label="text"
                  />
                  <b-form-invalid-feedback :state="errors.length > 0 ? false:null">
                    {{ errors[0] }}
                  </b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
          </b-row>
        </validation-observer>
      </tab-content> -->

      <!-- address  -->
      <tab-content
        title="Balances"
      >
        <!-- <validation-observer
          ref="addressRules"
          tag="form"
        > -->
          <b-row>
            <b-col
              cols="12"
              class="mb-2"
            >
              <h5 class="mb-0">
                Sistema de balance CMP
              </h5>
              <small class="text-muted">
                Seleccione una opción
              </small>
            </b-col>
            <b-col offset-md="4">
            <b-button
                v-ripple.400="'rgba(255, 255, 255, 0.15)'"
                type="button"
                variant="primary"
                class="mr-1"
                @click="create_new_balance"
            >
                Balance Nuevo
            </b-button>
            <b-button
                v-ripple.400="'rgba(186, 191, 199, 0.15)'"
                type="button"
                variant="warning"
                class="mr-1"
                @click="correr_tables"
            >
                Balances Ejecutados
            </b-button>
            </b-col>
            <!-- <b-button
                v-ripple.400="'rgba(255, 255, 255, 0.15)'"
                type="button"
                variant="warning"
                @click="correr_tables"
                v-show="correr_button"
            >
                Correr
            </b-button> -->
          </b-row>
          <br>
          <b-row>
            <b-col>
                <balance v-if="new_balance" ref="balances_ref"/>
            </b-col>
          </b-row>
      </tab-content>

      <!-- social link -->
      <!-- <tab-content
        title="Social Links"
        :before-change="validationFormSocial"
      >
        <validation-observer
          ref="socialRules"
          tag="form"
        >
          <b-row>
            <b-col
              cols="12"
              class="mb-2"
            >
              <h5 class="mb-0">
                Social Links
              </h5>
              <small class="text-muted">Enter Your Social Links</small>
            </b-col>
            <b-col md="6">
              <b-form-group
                label="Twitter"
                label-for="twitter"
              >
                <validation-provider
                  #default="{ errors }"
                  name="Twitter"
                  rules="required|url"
                >
                  <b-form-input
                    id="twitter"
                    v-model="twitterUrl"
                    :state="errors.length > 0 ? false:null"
                    placeholder="https://twitter.com/abc"
                  />
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>
            </b-col>
            <b-col md="6">
              <b-form-group
                label="Facebook"
                label-for="facebook"
              >
                <validation-provider
                  #default="{ errors }"
                  name="Facebook"
                  rules="required|url"
                >
                  <b-form-input
                    id="facebook"
                    v-model="facebookUrl"
                    :state="errors.length > 0 ? false:null"
                    placeholder="https://facebook.com/abc"
                  />
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>
            </b-col>
            <b-col md="6">
              <b-form-group
                label="Google+"
                label-for="google-plus"
              >
                <validation-provider
                  #default="{ errors }"
                  name="Google+"
                  rules="required|url"
                >
                  <b-form-input
                    id="google-plus"
                    v-model="googleUrl"
                    :state="errors.length > 0 ? false:null"
                    placeholder="https://plus.google.com/abc"
                  />
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>
            </b-col>
            <b-col md="6">
              <b-form-group
                label="LinkedIn"
                label-for="linked-in"
              >
                <validation-provider
                  #default="{ errors }"
                  name="LinkedIn"
                  rules="required|url"
                >
                  <b-form-input
                    id="linked-in"
                    v-model="linkedinUrl"
                    :state="errors.length > 0 ? false:null"
                    placeholder="https://linkedin.com/abc"
                  />
                  <small class="text-danger">{{ errors[0] }}</small>
                </validation-provider>
              </b-form-group>
            </b-col>
          </b-row>
        </validation-observer>
      </tab-content> -->
    </form-wizard>

    <!-- modal login-->
    <b-modal
      id="modal_guardar_balance"
      ref="my-modal"
      title="Guardar Balance"
      @show="resetModal"
      @hidden="resetModal"
      @ok="handleOk"
    >
      <form ref="form" @submit.stop.prevent="handleSubmit">
        <b-form-group
          label="Nombre Balance"
          label-for="nombre_balance"
          invalid-feedback="Nombre es requerido"
          :state="nameState"
        >
          <b-form-input
            id="nombre_balance"
            v-model="nombre_balance"
            :state="nameState"
            required
          ></b-form-input>
        </b-form-group>
      </form>
    </b-modal>

  </div>
</template>
<style lang="scss">
@import '~@core/scss/vue/libs/vue-wizard.scss';
@import '~@core/scss/vue/libs/vue-select.scss';
</style>
<script>
import { FormWizard, TabContent } from 'vue-form-wizard'
import vSelect from 'vue-select'
import { ValidationProvider, ValidationObserver } from 'vee-validate'
import ToastificationContent from '@core/components/toastification/ToastificationContent.vue'
import 'vue-form-wizard/dist/vue-form-wizard.min.css'
import axios from "axios";
import Balance from "./Balances.vue"
// import 'vue-wizard.scss'
import {
  BRow,
  BCol,
  BFormGroup,
  BFormInput,
  BFormSelect,
  BFormInvalidFeedback,
  BSpinner,
  BButton,
  BModal,
} from 'bootstrap-vue'
import { required, email } from '@validations'
import { codeIcon } from './code'
import Ripple from 'vue-ripple-directive'

export default {
  components: {
    BModal,
    Balance,
    ValidationProvider,
    ValidationObserver,
    FormWizard,
    TabContent,
    BRow,
    BCol,
    BFormGroup,
    BFormInput,
    vSelect,
    BFormSelect,
    BFormInvalidFeedback,
    // eslint-disable-next-line vue/no-unused-components
    ToastificationContent,
    BSpinner,
    BButton,
  },
  directives: {
    Ripple,
  },
  data() {
    return {
      nombre_balance: '',
      nameState: null,
      selectedContry: '',
      selectedLanguage: '',
      name: '',
      emailValue: '',
      PasswordValue: '',
      passwordCon: '',
      first_name: '',
      last_name: '',
      address: '',
      landMark: '',
      pincode: '',
      twitterUrl: '',
      facebookUrl: '',
      googleUrl: '',
      linkedinUrl: '',
      city: '',
      required,
      email,
      codeIcon,
      valle: '',
      proceso: '',
      valles: [],
      procesos: [],
      loading: false,
      new_balance: false,
    }
  },
  mounted(){
      console.log("mounted");
      console.log(this.valles);
        this.loading = true;
      axios
        .get("getValles/1")
        .then((response) => {
            console.log("response", response);
            this.valles = response.data.valles;

            console.log("valles", this.valles);
        })
        .catch(function (e) {
          console.log("FAILURE!!", e);
        }).finally(() => {
                        this.loading =  false
                    });
  },
  methods: {
      checkFormValidity() {
        const valid = this.$refs.form.checkValidity()
        this.nameState = valid
        return valid
      },
      resetModal() {
        this.nombre_balance = ''
        this.nameState = null
      },
      handleOk(bvModalEvent) {
        // Prevent modal from closing
        bvModalEvent.preventDefault()
        // Trigger submit handler
        this.handleSubmit()
      },
      handleSubmit() {
        // Exit when the form isn't valid
        if (!this.checkFormValidity()) {
          return
        }
        console.log("aqui se sube le nombre del balance", this.$refs.balances_ref.datos_entrada );
        axios
        .post("save_balance", {
            "datos_entrada": this.$refs.balances_ref.datos_entrada ,
            "nombre_balance": this.nombre_balance,
            }, {
          headers: {
            "Content-Type": "application/json",
          },
        })
        .then((response) => {
            this.$toast({
            component: ToastificationContent,
            props: {
            title: 'Se ha guardado el balance con exito',
            icon: 'EditIcon',
            variant: 'success',
            },
        })
        this.$refs['my-modal'].hide()
        })
        .catch(function (e) {
          console.log("FAILURE!! correr_balance", e);
          this.$toast({
            component: ToastificationContent,
            props: {
            title: 'Ocurrio un problema al intentar guardar el balance',
            icon: 'EditIcon',
            variant: 'danger',
            },
        })
        this.$refs['my-modal'].hide()
        });

        // Push the name to submitted names
        // this.submittedNames.push(this.nombre_balance)
        // Hide the modal manually
        this.$nextTick(() => {
          this.$bvModal.hide('modal-prevent-closing')
        })
      },


      create_new_balance(){
          this.new_balance = true;
      },
    valles_change(value){
        console.log("cambio en valles", value, this.valle);
        this.loading = true;
        axios
        .get("getProcesos/"+ this.valle)
        .then((response) => {
            console.log("response", response);
            this.procesos = response.data.procesos;
            console.log("los procesos", this.procesos);
        })
        .catch(function (e) {
          console.log("FAILURE!!", e);
        }).finally(() => {
                        this.loading =  false
                    });
    },
    formSubmitted() {
        this.$refs['my-modal'].show()
        //$bvModal.show('modal_guardar_balance');

    },
    validationForm() {
      return new Promise((resolve, reject) => {
        this.$refs.accountRules.validate().then(success => {
          if (success) {
            resolve(true)
          } else {
            reject()
          }
        })
      })
    },
    validationFormInfo() {
      return new Promise((resolve, reject) => {
        this.$refs.infoRules.validate().then(success => {
          if (success) {
            resolve(true)
          } else {
            reject()
          }
        })
      })
    },
    validationFormAddress() {
      return new Promise((resolve, reject) => {
        this.$refs.addressRules.validate().then(success => {
          if (success) {
            resolve(true)
          } else {
            reject()
          }
        })
      })
    },
    validationFormSocial() {
      return new Promise((resolve, reject) => {
        this.$refs.socialRules.validate().then(success => {
          if (success) {
            resolve(true)
          } else {
            reject()
          }
        })
      })
    },
  },
}
</script>
