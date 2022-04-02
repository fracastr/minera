<template>
  <div>
    <b-form @submit.prevent @submit="onSubmit">
      <b-row>
        <b-col cols="12">
          <b-form-group
            label="Archivo carga"
            label-for="h-archivo-carga"
            label-cols-md="4"
          >
            <b-form-file
              v-model="file_1"
              placeholder="Eliga su archivo"
              drop-placeholder="Suelte su archivo aqui"
              browse-text="Buscar"
              required
              ref="input_file1"
            />
          </b-form-group>
        </b-col>
        <b-col md="8" offset-md="4"> </b-col>

        <!-- submit and reset -->
        <b-col offset-md="4">
          <b-button
            v-ripple.400="'rgba(255, 255, 255, 0.15)'"
            type="submit"
            variant="primary"
            class="mr-1"
          >
            Cargar
          </b-button>
          <b-button
            v-ripple.400="'rgba(186, 191, 199, 0.15)'"
            type="reset"
            variant="outline-secondary"
            class="mr-1"
          >
            Reset
          </b-button>
          <b-button
            v-ripple.400="'rgba(255, 255, 255, 0.15)'"
            type="button"
            variant="warning"
            @click="correr_tables"
            v-show="correr_button"
          >
            Correr
          </b-button>
        </b-col>
      </b-row>
    </b-form>
    <hr>
    <b-container>
      <b-row cols="4">
        <b-col md="4" sm="12">
          <b-table-lite
            hover
            ref="table1"
            title="Balances"
            responsive
            :items="balances_table"
            sticky-header="500px"
            v-on:scroll.native="scrolled"
          />
        </b-col>
        <b-col md="8" sm="12">
          <b-table-lite
            hover
            ref="restricciones"
            title="Restricciones"
            responsive
            :items="restricciones_table"
            :fields="restricciones_fields"
            sticky-header="500px"
            v-show="show_tables"
          />
        </b-col>
        <!-- <b-col md="4" sm="12">
          <b-table
            ref="resultado_restricciones"
            title="Resultado_restricciones"
            responsive
            :items="resultado_restricciones"
            sticky-header="500px"
            v-on:scroll.native="scrolled"
          />
        </b-col> -->
        <b-col md="4" sm="12" offset-md="4">
          <b-table-lite
            hover
            ref="balance_nodos"
            title="Balance_nodos"
            responsive
            :items="balance_nodos"
            sticky-header="500px"
            v-show="show_tables"
          />
        </b-col>
      </b-row>
    </b-container>
  </div>
</template>

<script>
import {
  BRow,
  BCol,
  BFormGroup,
  BFormInput,
  BFormCheckbox,
  BForm,
  BButton,
  BFormFile,
  BTable,
  BTableLite
} from "bootstrap-vue";
import Ripple from "vue-ripple-directive";
import axios from "axios";

export default {
  data() {
    return {
      file_1: null,
      isBusy: false,
      balances_table: [],
      restricciones_table: [],
      resultado_restricciones: [],
      balance_nodos: [],
      restricciones_fields: [],
      correr_button: false,
      show_tables: false,
      datos_entrada: {},
    };
  },
  mounted(){
      console.log("mounted");
      console.log(this.$refs);
  },
  methods: {
      scrolled(e){
          console.log("estoy haciendo scroll",e);
          console.log(this.$refs.restricciones);

          this.$refs.restricciones.scrollHeight = e.target.scrollHeight;
          this.$refs.restricciones.scrollLeft = e.target.scrollLeft;
      },
    correr_tables(event) {
        console.log(this.datos_entrada);
      axios
        .post("correr_balance", {"datos_entrada": this.datos_entrada}, {
          headers: {
            "Content-Type": "application/json",
          },
        })
        .then((response) => {
          this.balances_table = response.data.balances_table;
          this.restricciones_table = response.data.restricciones_table;
          //this.resultado_restricciones = response.data.resultado_restricciones;
          this.balance_nodos = response.data.balance_nodos;
          this.restricciones_fields = response.data.restricciones_fields;
          this.datos_entrada = response.data.datos_entrada;
        })
        .catch(function (e) {
          console.log("FAILURE!! correr_balance", e);
        });
      this.show_tables = true;
    },
    onSubmit(event) {
      event.preventDefault();
      let formData = new FormData();
      formData.append("file", this.file_1);

      axios
        .post("import", formData, {
          headers: {
            "Content-Type": "multipart/form-data",
          },
        })
        .then((response) => {
          this.balances_table = response.data.balances_table;
          this.restricciones_table = response.data.restricciones_table;
          //this.resultado_restricciones = response.data.resultado_restricciones;
          this.balance_nodos = response.data.balance_nodos;
          this.restricciones_fields = response.data.restricciones_fields;
          this.datos_entrada = response.data.data.datos_entrada;
          this.correr_button = true;
          console.log("items after call");
          console.log(this.datos_entrada);
        })
        .catch(function (e) {
          console.log("FAILURE!!", e);
        });
    },
  },
  components: {
    BRow,
    BCol,
    BFormGroup,
    BFormInput,
    BFormCheckbox,
    BForm,
    BButton,
    BFormFile,
    BTable,
    BTableLite,
  },
  directives: {
    Ripple,
  },
};
</script>
