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
                <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" />
                <ag-grid-vue style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="balances_fields"
                    :rowData="balances_table"
                    @grid-ready="onGridReadyBalancesTable"
                    :getRowStyle="getRowStyle"
                    @bodyScroll="scrolledbalances"
                    id="tbalances">
                </ag-grid-vue>
            </b-col>
            <b-col md="8" sm="12">
                <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" />
                <ag-grid-vue style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="restricciones_fields"
                    :rowData="restricciones_table"
                    @grid-ready="onGridReadyRestriccionesTable"
                    :getRowStyle="getRowStyle"
                    @bodyScroll="scrolledrestricciones"
                    id="trestricciones">
                </ag-grid-vue>
            </b-col>
        </b-row>
        <br>
        <b-row cols="4">
            <b-col md="8" sm="12" offset-md="2">
                <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" />
                <ag-grid-vue style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="balance_nodos_fields"
                    :rowData="balance_nodos"
                    @row-clicked="onRowClicked">
                </ag-grid-vue>
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
import { AgGridVue } from "ag-grid-vue";
function decimalFormatter(params) {
    // console.log("params decimal", params.value);
    // console.log(parseFloat(params.value));
    return (parseFloat(params.value) * 100).toFixed(2);
}
function intFormatter(params) {
    // console.log("params int", params.value);
    // console.log(parseInt(params.value));
    return parseInt(params.value.replace(/,/g, ''), 10)
    // return (parseFloat(params.value) * 100).toFixed(2);
}

function nodosCellStyle(params) {
    console.log("nodosStyle", params);
    console.log(!isNaN(params.value));
    console.log(parseFloat(params.value));
    if(!isNaN(params.value) && parseFloat(params.value) > 0.001){
        return {
            backgroundColor: "red",
        };
    }
};

export default {
  data() {
    return {
      file_1: null,
      isBusy: false,
      balances_table: [],
      restricciones_table: [],
      resultado_restricciones: [],
      balance_nodos: [],
      balance_nodos_fields: [],
      restricciones_fields: [],
      balances_fields: [],
      correr_button: false,
      show_tables: false,
      datos_entrada: {},
      columnDefs: null,
      rowData: null,
      datos_entrada_id: null,
      yellow: [],
      green: [],
      gridApiBalancesTable: null,
      gridColumnApiBalancesTable: null,
      gridApiRestriccionesTable: null,
      columnApiRestriccionesTable: null,
    };
  },
  mounted(){
      console.log("mounted");
      console.log(this.$refs);
  },
  beforeMount() {
    this.columnDefs = [
    { field: 'make', editable: true },
    { field: 'model', editable: true },
    { field: 'price', editable: true }
];

    this.rowData = [
      { make: "Toyota", model: "Celica", price: 35000 },
      { make: "Ford", model: "Mondeo", price: 32000 },
      { make: "Porsche", model: "Boxter", price: 72000 },
    ];
  },
  methods: {
    onGridReadyBalancesTable(params) {
      this.gridApiBalancesTable = params.api;
      this.gridColumnApiBalancesTable = params.columnApi;
    },
    onGridReadyRestriccionesTable(params) {
      this.gridApiRestriccionesTable = params.api;
      this.columnApiRestriccionesTable = params.columnApi;
    },
    getRowStyle(params){
    //console.log("rowstyle", params);
    // if (params.node.rowIndex % 2 === 0) {
    //     return { background: 'yellow' };
    // }
    //console.log("yellow", this.yellow);
    let yellow = this.yellow;
    let green = this.green;
    let resp = "";

    if(yellow.length > 0){
        yellow.map(function(value, index){
            //console.log(params.node.rowIndex, value);
            if (params.node.rowIndex === value) {
                console.log("valido");
                resp =  { background: 'yellow' };
            }
        })
    }
    if(green.length > 0){
        green.map(function(value, index){
            //console.log(params.node.rowIndex, value);
            if (params.node.rowIndex === value) {
                console.log("valido");
                resp =  { background: 'green' };
            }
        })
    }

    return resp;
    },
      scrolledbalances(e){
        //   console.log("estoy haciendo scroll",e);
        //   console.log("tabla restricciones", this.gridApiRestriccionesTable);
        const tbl1 = document.getElementById("tbalances");
        const tbl2 = document.getElementById("trestricciones");
        // console.log("tabla 2", tbl2);
        // const gridBody1 = tbl1.querySelector(".ag-body-viewport");
        const gridBody2 = tbl2.querySelector(".ag-body-viewport");
        // console.log("gridBody2", gridBody2);

        gridBody2.scrollTop = e.top;

      },
      scrolledrestricciones(e){
        //   console.log("estoy haciendo scroll",e);
        //   console.log("tabla restricciones", this.gridApiRestriccionesTable);
        // const tbl1 = document.getElementById("tbalances");
        // const tbl2 = document.getElementById("trestricciones");
        // // console.log("tabla 2", tbl2);
        // const gridBody1 = tbl1.querySelector(".ag-body-viewport");
        // const gridBody2 = tbl2.querySelector(".ag-body-viewport");
        // console.log("gridBody2", gridBody2);

        // gridBody1.scrollTop = e.top;

      },
    onRowClicked(event) {
        console.log("se ha hecho click en una fila");
        console.log(event);
        // llamado al endpoint
        axios
        .post("paint_tables", {
            "datos_entrada_id": this.datos_entrada_id,
            "rowIndex": event.rowIndex
            }, {
          headers: {
            "Content-Type": "application/json",
          },
        })
        .then((response) => {
            this.yellow = response.data.yellow;
            this.green = response.data.green;

            this.gridApiBalancesTable.redrawRows();
            this.gridApiRestriccionesTable.redrawRows();
        })
        .catch(function (e) {
          console.log("FAILURE!! correr_balance", e);
        });

    },
    correr_tables(event) {
        console.log(this.datos_entrada);
      axios
        .post("correr_balance", {
            "datos_entrada": this.datos_entrada,
            "datos_entrada_id": this.datos_entrada_id,
            "balances_table": this.balances_table,
            "restricciones_table": this.restricciones_table,
            "balance_nodos": this.balance_nodos
            }, {
          headers: {
            "Content-Type": "application/json",
          },
        })
        .then((response) => {
          this.balances_table = response.data.balances_table;
          this.restricciones_table = response.data.restricciones_table;
          //this.resultado_restricciones = response.data.resultado_restricciones;
          this.balance_nodos = response.data.balance_nodos;
          this.balance_nodos_fields = response.data.balance_nodos_fields;
          this.restricciones_fields = response.data.restricciones_fields;
          this.balances_fields = response.data.balances_fields;
          this.datos_entrada = response.data.data.datos_entrada;
          this.datos_entrada_id = response.data.datos_entrada_id;
            //console.log("response", response);
          // armar formateo dinamico de numeros
          this.balances_fields.map(function(value, index){
            //   console.log("index y value");
              if(index === 1 || index === 2){
                  value.valueFormatter = intFormatter;
              }
              if(index === 3 || index === 4){
                  value.valueFormatter = decimalFormatter;
              }
          })
        let data_restricciones = this.restricciones_fields;
          // armar formateo dinamico de numeros tabla 2
          this.restricciones_fields.map(function(value, index){
              console.log("index y value", index, value, data_restricciones.length);
              if(index != data_restricciones.length - 1){
                  console.log(index);
                  value.valueFormatter = decimalFormatter;
              }
          })

          this.balance_nodos_fields.map(function(value, index){
            value.cellStyle = nodosCellStyle;

          })
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
          this.balance_nodos_fields = response.data.balance_nodos_fields;
          this.restricciones_fields = response.data.restricciones_fields;
          this.balances_fields = response.data.balances_fields;
          this.datos_entrada = response.data.data.datos_entrada;
          this.datos_entrada_id = response.data.datos_entrada_id;
          this.correr_button = true;
          //console.log("items after call");
          console.log(this.datos_entrada);

          // armar formateo dinamico de numeros
          this.balances_fields.map(function(value, index){
            //   console.log("index y value");
              if(index === 1 || index === 2){
                  value.valueFormatter = intFormatter;
              }
              if(index === 3 || index === 4){
                  value.valueFormatter = decimalFormatter;
              }
          })
        let data_restricciones = this.restricciones_fields;
          // armar formateo dinamico de numeros tabla 2
          this.restricciones_fields.map(function(value, index){
              //console.log("index y value", index, value, data_restricciones.length);
              if(index != data_restricciones.length - 1){
                  console.log(index);
                  value.valueFormatter = decimalFormatter;
              }
          })

          this.balance_nodos_fields.map(function(value, index){
            value.cellStyle = nodosCellStyle;

          })
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
    AgGridVue,
  },
  directives: {
    Ripple,
  },
};
</script>

<style lang="scss">
  @import "~ag-grid-community/dist/styles/ag-grid.css";
  @import "~ag-grid-community/dist/styles/ag-theme-material.css";
  @import "~ag-grid-community/dist/styles/ag-theme-alpine.css";
</style>
