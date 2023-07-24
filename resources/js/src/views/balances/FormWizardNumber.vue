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
      <tab-content
        title="Balances"
      >
          <br>
          <b-row>
            <b-col>
                <balance v-if="true" ref="balances_ref" :proceso="proceso" :show_tables="show_tables"/>
            </b-col>
          </b-row>
      </tab-content>
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
      proceso: 0,
      valles: [],
      procesos: [],
      loading: false,
      new_balance: false,
      balance_id: null,
      show_tables: false,
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
            "datos_entrada_id": this.$refs.balances_ref.datos_entrada_id,
            "proceso_id": this.proceso
            }, {
          headers: {
            "Content-Type": "application/json",
          },
        })
        .then((response) => {
            this.balance_id = response.data.balance_id;
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
        this.show_tables = false;
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
